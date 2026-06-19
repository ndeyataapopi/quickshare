<?php

namespace App\Modules\Collections\Services;

use App\Models\User;
use App\Modules\Collections\Models\CollectionLog;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\Repayment;
use App\Modules\TrustScore\Services\TrustScoreService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CollectionService
{
    // ─── Reminder Schedules ───────────────────────────────────────────

    const REMINDER_SCHEDULE = [
        7 => 'pre_due',      // 7 days before
        3 => 'pre_due',      // 3 days before
        1 => 'due_today',    // Due today
        -1 => 'overdue_1',   // 1 day overdue
        -3 => 'overdue_3',   // 3 days overdue
        -7 => 'overdue_7',   // 7 days overdue (escalation)
        -14 => 'overdue_14', // 14 days overdue (level 2)
        -30 => 'overdue_30', // 30 days overdue (level 3)
    ];

    const ESCALATION_LEVELS = [
        'escalation_level_1' => 7,   // Days overdue
        'escalation_level_2' => 14,
        'escalation_level_3' => 30,
    ];

    // ─── Send Reminder ───────────────────────────────────────────────

    public function sendReminder(
        Loan $loan,
        Repayment $repayment,
        string $channel,
        string $template,
        ?string $message = null,
    ): CollectionLog {
        return DB::transaction(function () use ($loan, $repayment, $channel, $template, $message) {
            // Check if reminder already sent recently (prevent spam)
            $recentReminder = CollectionLog::forLoan($loan->id)
                ->byAction('reminder_sent')
                ->byChannel($channel)
                ->whereDate('created_at', '>=', now()->subDay())
                ->first();

            if ($recentReminder) {
                Log::info('Skipping duplicate reminder', [
                    'loan_id' => $loan->id,
                    'channel' => $channel,
                    'last_reminder' => $recentReminder->created_at,
                ]);
                return $recentReminder;
            }

            $log = CollectionLog::create([
                'loan_id' => $loan->id,
                'borrower_id' => $loan->borrower_id,
                'repayment_id' => $repayment->id,
                'action_type' => 'reminder_sent',
                'channel' => $channel,
                'template_used' => $template,
                'message' => $message ?? $this->getReminderMessage($template, $loan, $repayment),
                'status' => 'pending',
            ]);

            // Dispatch notification job
            dispatch(new \App\Modules\Collections\Jobs\SendNotificationJob($log->id));

            return $log;
        });
    }

    // ─── Process Daily Reminders ────────────────────────────────────

    public function processDailyReminders(): array
    {
        $stats = ['sent' => 0, 'skipped' => 0, 'failed' => 0];

        // Get all active loans with upcoming or overdue repayments
        $loans = Loan::where('status', 'active')
            ->whereHas('repayments', function ($q) {
                $q->whereIn('status', ['pending', 'overdue']);
            })
            ->with(['repayments' => function ($q) {
                $q->whereIn('status', ['pending', 'overdue']);
            }])
            ->get();

        foreach ($loans as $loan) {
            foreach ($loan->repayments as $repayment) {
                // Use ceil to handle fractional days properly
                $daysUntilDue = (int) ceil(now()->diffInDays($repayment->due_date, false));
                
                // Find if we should send reminder based on schedule
                $reminderType = self::REMINDER_SCHEDULE[$daysUntilDue] ?? null;

                if ($reminderType) {
                    // Send multi-channel reminders
                    $channels = $this->determineChannels($loan->borrower, $daysUntilDue);
                    
                    foreach ($channels as $channel) {
                        try {
                            $this->sendReminder($loan, $repayment, $channel, $reminderType);
                            $stats['sent']++;
                        } catch (\Throwable $e) {
                            Log::error('Failed to send reminder', [
                                'loan_id' => $loan->id,
                                'channel' => $channel,
                                'error' => $e->getMessage(),
                            ]);
                            $stats['failed']++;
                        }
                    }
                }
            }
        }

        return $stats;
    }

    // ─── Escalation Workflow ─────────────────────────────────────────

    public function processEscalations(): array
    {
        $stats = [
            'escalation_level_1' => 0,
            'escalation_level_2' => 0,
            'escalation_level_3' => 0,
        ];

        foreach (self::ESCALATION_LEVELS as $level => $daysOverdue) {
            $repayments = Repayment::overdue()
                ->where('days_overdue', '>=', $daysOverdue)
                ->whereDoesntHave('collectionLogs', function ($q) use ($level) {
                    $q->where('action_type', $level);
                })
                ->with('loan')
                ->get();

            foreach ($repayments as $repayment) {
                $loan = $repayment->loan;

                // Log escalation
                CollectionLog::create([
                    'loan_id' => $loan->id,
                    'borrower_id' => $loan->borrower_id,
                    'repayment_id' => $repayment->id,
                    'action_type' => $level,
                    'channel' => 'system',
                    'message' => "Escalated to {$level} after {$daysOverdue} days overdue",
                    'status' => 'delivered',
                    'sent_at' => now(),
                    'delivered_at' => now(),
                ]);

                // Trigger trust score penalty
                $this->applyTrustScorePenalty($loan->borrower, $level);

                // Notify referrer if applicable
                $this->notifyReferrer($loan->borrower, $repayment);

                $stats[$level]++;
            }
        }

        return $stats;
    }

    // ─── Default Workflow ────────────────────────────────────────────

    public function processDefaultWorkflow(Loan $loan): void
    {
        DB::transaction(function () use ($loan) {
            // Log default initiation
            CollectionLog::create([
                'loan_id' => $loan->id,
                'borrower_id' => $loan->borrower_id,
                'action_type' => 'default_initiated',
                'channel' => 'system',
                'message' => 'Loan marked as defaulted - initiating default workflow',
                'status' => 'delivered',
                'sent_at' => now(),
                'delivered_at' => now(),
            ]);

            // Heavy trust score penalty
            $trustService = app(TrustScoreService::class);
            $trustService->adjustScore(
                $loan->borrower,
                TrustScoreService::WEIGHT_REPAYMENT_DEFAULT,
                'Loan defaulted',
                'loan_defaulted',
                ['loan_id' => $loan->id],
            );

            // Penalize referrer
            $this->penalizeReferrer($loan->borrower);

            // Log final default
            CollectionLog::create([
                'loan_id' => $loan->id,
                'borrower_id' => $loan->borrower_id,
                'action_type' => 'default_processed',
                'channel' => 'system',
                'message' => 'Default workflow completed - trust score and referrer penalties applied',
                'status' => 'delivered',
                'sent_at' => now(),
                'delivered_at' => now(),
            ]);

            Log::info('Default workflow processed', [
                'loan_id' => $loan->id,
                'borrower_id' => $loan->borrower_id,
            ]);
        });
    }

    // ─── Referral Accountability ────────────────────────────────────

    protected function notifyReferrer(User $borrower, Repayment $repayment): void
    {
        $referrer = $borrower->referrer;

        if (! $referrer) {
            return;
        }

        // Log referrer notification
        CollectionLog::create([
            'loan_id' => $repayment->loan_id,
            'borrower_id' => $borrower->id,
            'repayment_id' => $repayment->id,
            'action_type' => 'referral_notified',
            'channel' => 'email',
            'message' => "Referrer notified: {$referrer->email} about overdue repayment",
            'status' => 'pending',
        ]);

        // Dispatch notification to referrer
        dispatch(new \App\Modules\Collections\Jobs\NotifyReferrerJob(
            $referrer,
            $borrower,
            $repayment,
        ));
    }

    protected function penalizeReferrer(User $borrower): void
    {
        $referrer = $borrower->referrer;

        if (! $referrer) {
            return;
        }

        $trustService = app(TrustScoreService::class);
        $trustService->adjustScore(
            $referrer,
            TrustScoreService::WEIGHT_REFERRAL_DEFAULTED,
            'Referred user defaulted on loan',
            'referral_defaulted',
            ['referred_user_id' => $borrower->id],
        );

        Log::info('Referrer penalized for default', [
            'referrer_id' => $referrer->id,
            'borrower_id' => $borrower->id,
        ]);
    }

    // ─── Trust Score Penalty ─────────────────────────────────────────

    protected function applyTrustScorePenalty(User $borrower, string $level): void
    {
        $penalties = [
            'escalation_level_1' => -3.00,
            'escalation_level_2' => -5.00,
            'escalation_level_3' => -8.00,
        ];

        $penalty = $penalties[$level] ?? -2.00;

        $trustService = app(TrustScoreService::class);
        $trustService->adjustScore(
            $borrower,
            $penalty,
            "Collection escalation: {$level}",
            'collection_escalation',
            ['level' => $level],
        );
    }

    // ─── Determine Channels ───────────────────────────────────────────

    protected function determineChannels(User $borrower, int $daysUntilDue): array
    {
        // Pre-due: Email + SMS
        if ($daysUntilDue > 0) {
            return ['email', 'sms'];
        }

        // Overdue: Add WhatsApp for higher engagement
        $channels = ['email', 'sms'];
        
        if ($daysUntilDue <= -3) {
            $channels[] = 'whatsapp';
        }

        // High escalation: Add phone call reminder
        if ($daysUntilDue <= -14) {
            $channels[] = 'voice';
        }

        return $channels;
    }

    // ─── Get Reminder Message ────────────────────────────────────────

    protected function getReminderMessage(string $template, Loan $loan, Repayment $repayment): string
    {
        $messages = [
            'pre_due' => "Reminder: Your loan {$loan->reference} payment of R{$repayment->amount} is due on {$repayment->due_date->toDateString()}.",
            'due_today' => "URGENT: Your loan {$loan->reference} payment of R{$repayment->amount} is due TODAY. Please make payment to avoid penalties.",
            'overdue_1' => "Your loan {$loan->reference} payment is now 1 day overdue. Please pay immediately to avoid additional fees.",
            'overdue_3' => "OVERDUE NOTICE: Loan {$loan->reference} is 3 days overdue. Late fees are accruing. Contact us to discuss.",
            'overdue_7' => "FINAL NOTICE: Loan {$loan->reference} is severely overdue. This will affect your credit score. Immediate payment required.",
            'overdue_14' => "ESCALATED: Loan {$loan->reference} is 14+ days overdue. Legal action may be initiated. Contact collections immediately.",
            'overdue_30' => "LEGAL WARNING: Loan {$loan->reference} default imminent. Contact us within 48 hours to avoid legal proceedings.",
        ];

        return $messages[$template] ?? "Payment reminder for loan {$loan->reference}";
    }

    // ─── Queries ─────────────────────────────────────────────────────

    public function getCollectionHistory(int $loanId)
    {
        return CollectionLog::forLoan($loanId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getBorrowerCollectionStats(int $borrowerId): array
    {
        $logs = CollectionLog::forBorrower($borrowerId);

        return [
            'total_reminders' => (clone $logs)->reminders()->count(),
            'total_escalations' => (clone $logs)->escalations()->count(),
            'successful_contacts' => (clone $logs)->where('response_received', true)->count(),
            'last_contact' => (clone $logs)->delivered()->latest()->first()?->created_at,
        ];
    }

    public function getCollectionsDashboard(): array
    {
        return [
            'overdue_today' => Repayment::overdue()->whereDate('due_date', today())->count(),
            'overdue_week' => Repayment::overdue()->whereDate('due_date', '>=', now()->subDays(7))->count(),
            'escalation_level_1' => Repayment::overdue()->whereBetween('days_overdue', [7, 13])->count(),
            'escalation_level_2' => Repayment::overdue()->whereBetween('days_overdue', [14, 29])->count(),
            'escalation_level_3' => Repayment::overdue()->where('days_overdue', '>=', 30)->count(),
            'reminders_sent_today' => CollectionLog::reminders()->whereDate('created_at', today())->count(),
            'delivery_rate' => $this->calculateDeliveryRate(),
        ];
    }

    protected function calculateDeliveryRate(): float
    {
        $total = CollectionLog::reminders()->whereDate('created_at', today())->count();
        if ($total === 0) {
            return 100.0;
        }

        $delivered = CollectionLog::reminders()
            ->delivered()
            ->whereDate('created_at', today())
            ->count();

        return round(($delivered / $total) * 100, 1);
    }

    // ─── Resolve Collection Case ───────────────────────────────────────

    public function resolveCase(\App\Modules\Collections\Models\CollectionCase $case, array $data): void
    {
        $case->update([
            'status' => 'resolved',
            'resolution' => $data['resolution'],
            'amount_recovered' => $data['amount_recovered'],
            'resolution_notes' => $data['notes'] ?? null,
        ]);

        // Update loan status if fully recovered
        if ($data['resolution'] === 'paid_in_full' && $case->loan) {
            $case->loan->update(['status' => 'completed']);
        }
    }
}
