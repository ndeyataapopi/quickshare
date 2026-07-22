<?php

namespace App\Modules\Repayments\Services;

use App\Exceptions\ApiException;
use App\Models\User;
use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Repayments\Events\LoanFullyRepaid;
use App\Modules\Repayments\Events\RepaymentMade;
use App\Modules\Repayments\Events\RepaymentOverdue;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepaymentService
{
    public function __construct(protected LoanService $loanService)
    {
    }

    // ─── Create Repayment Schedule ───────────────────────────────────

    public function createRepaymentSchedule(Loan $loan): Repayment
    {
        return DB::transaction(function () use ($loan) {
            // Check if schedule already exists
            $existing = Repayment::forLoan($loan->id)->first();
            if ($existing) {
                throw new ApiException('Repayment schedule already exists for this loan.', 422);
            }

            // Calculate due date from loan term
            $dueDate = $loan->repayment_date ?? now()->addDays($loan->loan_term_days);

            // Create single repayment for now (bullet repayment)
            // Future: could create amortized schedule with multiple payments
            $schedule = $this->loanService->repaymentCalculation($loan);

            $repayment = Repayment::create([
                'loan_id' => $loan->id,
                'borrower_id' => $loan->borrower_id,
                'amount' => $schedule['amount'],
                'principal' => $schedule['principal'],
                'interest' => $schedule['lender_return'],
                'platform_fee' => $schedule['platform_fee'],
                'penalty' => 0,
                'status' => 'pending',
                'due_date' => $dueDate,
                'transaction_reference' => Repayment::generateReference(),
            ]);

            Log::info('Repayment schedule created', [
                'loan_id' => $loan->id,
                'repayment_id' => $repayment->id,
                'due_date' => $dueDate->toDateString(),
                'amount' => $repayment->amount,
            ]);

            return $repayment;
        });
    }

    // ─── Record Repayment ────────────────────────────────────────────

    public function recordRepayment(
        Loan $loan,
        User $borrower,
        float $amount,
        ?string $paymentMethod = 'bank_transfer',
        ?string $externalReference = null,
    ): Repayment {
        return DB::transaction(function () use ($loan, $borrower, $amount, $paymentMethod, $externalReference) {
            // Lock loan for update
            $lockedLoan = Loan::lockForUpdate()->find($loan->id);

            if (! $lockedLoan) {
                throw new ApiException('Loan not found.', 404);
            }

            if (! $lockedLoan->isActive()) {
                throw new ApiException('Loan is not active. Status: ' . $lockedLoan->status, 422);
            }

            // Find or create repayment record
            $repayment = Repayment::forLoan($loan->id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->first();

            if (! $repayment) {
                // Create new repayment record
                $repayment = $this->createRepaymentSchedule($lockedLoan);
            }

            // Calculate how much is still owed
            $totalDue = $repayment->amount + $repayment->penalty;
            $alreadyPaid = $this->getTotalPaidForRepayment($repayment);
            $remaining = $totalDue - $alreadyPaid;

            if ($amount > $remaining) {
                throw new ApiException(
                    "Payment amount exceeds remaining balance. Remaining: {$remaining}, Offered: {$amount}",
                    422,
                );
            }

            // Update repayment record
            $newPaidAmount = $alreadyPaid + $amount;
            $isFullyPaid = $newPaidAmount >= $totalDue;

            $repayment->update([
                'status' => $isFullyPaid ? 'paid' : 'partial',
                'paid_date' => now(),
                'external_reference' => $externalReference,
                'payment_method' => $paymentMethod,
                'metadata' => array_merge($repayment->metadata ?? [], [
                    'payment_history' => ($repayment->metadata['payment_history'] ?? []) + [
                        [
                            'amount' => $amount,
                            'date' => now()->toIso8601String(),
                            'reference' => $externalReference,
                        ],
                    ],
                ]),
            ]);

            // Split among lenders
            $this->distributeToLenders($repayment, $amount);

            // Fire repayment event
            RepaymentMade::dispatch($loan->id, $borrower, $amount);

            // Check if loan is fully repaid
            if ($isFullyPaid) {
                $this->markLoanAsFullyRepaid($lockedLoan, $repayment);
            }

            Log::info('Repayment recorded', [
                'loan_id' => $loan->id,
                'repayment_id' => $repayment->id,
                'amount' => $amount,
                'status' => $repayment->status,
                'is_fully_paid' => $isFullyPaid,
            ]);

            return $repayment->fresh();
        });
    }

    // ─── Submit Repayment Request (Stage 10) ──────────────────────────

    /**
     * Borrower submits a repayment request for one or multiple installments.
     * - Sets each selected repayment to 'pending_approval' status.
     * - Creates an incoming DisbursementTransaction (Borrower → QuickShare).
     * - Does NOT reduce loan balance or update investor earnings.
     *
     * @param  array  $repaymentIds  IDs of repayments the borrower wants to pay
     * @param  User   $borrower      The authenticated borrower
     * @param  string $paymentMethod eft, mobile_wallet, cash_deposit
     * @param  string|null $paymentProofPath  Stored file path of uploaded proof
     * @param  string|null $externalReference  Optional payment reference from borrower
     * @return array{repayments: \Illuminate\Support\Collection, disbursement: DisbursementTransaction}
     */
    public function submitRepaymentRequest(
        array $repaymentIds,
        User $borrower,
        string $paymentMethod,
        ?string $paymentProofPath = null,
        ?string $externalReference = null,
    ): array {
        return DB::transaction(function () use ($repaymentIds, $borrower, $paymentMethod, $paymentProofPath, $externalReference) {
            // Fetch repayments, ensure they belong to the borrower and are in a submittable state
            $repayments = Repayment::whereIn('id', $repaymentIds)
                ->where('borrower_id', $borrower->id)
                ->whereIn('status', ['pending', 'partial', 'overdue'])
                ->lockForUpdate()
                ->get();

            if ($repayments->isEmpty()) {
                throw new ApiException('No eligible repayments found for submission.', 422);
            }

            // Group by loan to create one incoming disbursement per loan
            $byLoan = $repayments->groupBy('loan_id');

            $disbursements = [];

            foreach ($byLoan as $loanId => $loanRepayments) {
                $loan = Loan::lockForUpdate()->find($loanId);

                if (! $loan) {
                    throw new ApiException("Loan #{$loanId} not found.", 404);
                }

                if (! $loan->isActive()) {
                    throw new ApiException('Loan is not active. Status: ' . $loan->status, 422);
                }

                $totalAmount = $loanRepayments->sum(function ($r) {
                    return (float) $r->amount + (float) $r->penalty;
                });

                // Update each repayment to pending_approval
                foreach ($loanRepayments as $repayment) {
                    $repayment->update([
                        'status' => 'pending_approval',
                        'payment_method' => $paymentMethod,
                        'payment_proof_path' => $paymentProofPath,
                        'external_reference' => $externalReference,
                        'metadata' => array_merge($repayment->metadata ?? [], [
                            'submission' => [
                                'amount' => (float) $repayment->amount + (float) $repayment->penalty,
                                'date' => now()->toIso8601String(),
                                'payment_method' => $paymentMethod,
                                'reference' => $externalReference,
                            ],
                        ]),
                    ]);
                }

                // Create incoming disbursement (Borrower → QuickShare)
                $disbursement = DisbursementTransaction::create([
                    'loan_id' => $loanId,
                    'direction' => 'incoming',
                    'gross_amount' => $totalAmount,
                    'platform_fee' => 0,
                    'net_amount' => $totalAmount,
                    'status' => 'awaiting_approval',
                    'transaction_reference' => DisbursementTransaction::generateReference(),
                    'external_reference' => $externalReference,
                    'payment_method' => $paymentMethod,
                    'payment_proof_path' => $paymentProofPath,
                ]);

                $disbursements[] = $disbursement;

                Log::info('Repayment request submitted', [
                    'loan_id' => $loanId,
                    'borrower_id' => $borrower->id,
                    'repayment_ids' => $loanRepayments->pluck('id')->toArray(),
                    'total_amount' => $totalAmount,
                    'disbursement_id' => $disbursement->id,
                ]);
            }

            return [
                'repayments' => $repayments->fresh(),
                'disbursements' => collect($disbursements),
            ];
        });
    }

    // ─── Distribute to Lenders Proportionally ─────────────────────────

    protected function distributeToLenders(Repayment $repayment, float $amount): void
    {
        $loan = $repayment->loan;
        
        // Get all confirmed funding transactions for this loan
        $fundings = FundingTransaction::forLoan($loan->id)
            ->where('status', 'confirmed')
            ->get();

        if ($fundings->isEmpty()) {
            Log::warning('No funding transactions found for lender distribution', [
                'loan_id' => $loan->id,
                'repayment_id' => $repayment->id,
            ]);
            return;
        }

        $totalFunded = $fundings->sum('amount');

        foreach ($fundings as $funding) {
            $distribution = $this->loanService->lenderRepaymentDistribution(
                $loan,
                $funding,
                $totalFunded,
                $amount,
            );

            LenderRepayment::create([
                'repayment_id' => $repayment->id,
                'lender_id' => $funding->lender_id,
                'funding_transaction_id' => $funding->id,
                'amount' => $distribution['amount'],
                'principal_return' => $distribution['principal_return'],
                'interest_earned' => $distribution['interest_earned'],
                'penalty_share' => 0, // Penalties go to platform, not lenders
                'funding_percentage' => $distribution['funding_percentage'],
                'status' => 'pending',
                'transaction_reference' => LenderRepayment::generateReference(),
            ]);
        }
    }

    // ─── Check and Process Overdue Repayments ─────────────────────────

    public function checkOverdueRepayments(): int
    {
        $overdueCount = 0;

        $repayments = Repayment::shouldBeOverdue()->get();

        foreach ($repayments as $repayment) {
            // Calculate days overdue - diffInDays returns positive when first date > second date
            $daysOverdue = (int) $repayment->due_date->diffInDays(now(), false);
            $daysOverdue = max(0, $daysOverdue);

            // Calculate penalty
            $penalty = $this->calculatePenalty($repayment, $daysOverdue);

            $repayment->update([
                'status' => 'overdue',
                'days_overdue' => $daysOverdue,
                'penalty' => $penalty,
            ]);

            // Fire overdue event
            RepaymentOverdue::dispatch(
                $repayment->loan_id,
                $repayment->borrower,
                $daysOverdue,
            );

            $overdueCount++;

            Log::info('Repayment marked as overdue', [
                'repayment_id' => $repayment->id,
                'loan_id' => $repayment->loan_id,
                'days_overdue' => $daysOverdue,
                'penalty' => $penalty,
            ]);
        }

        return $overdueCount;
    }

    // ─── Calculate Penalty ───────────────────────────────────────────

    public function calculatePenalty(Repayment $repayment, int $daysOverdue): float
    {
        return $this->loanService->penaltyForRepayment($repayment, $daysOverdue);
    }

    // ─── Mark Loan as Fully Repaid ───────────────────────────────────

    protected function markLoanAsFullyRepaid(Loan $loan, Repayment $repayment): void
    {
        $loan->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        // Process all pending lender repayments
        LenderRepayment::forRepayment($repayment->id)
            ->where('status', 'pending')
            ->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);

        // Fire loan fully repaid event
        LoanFullyRepaid::dispatch($loan->id);

        Log::info('Loan fully repaid', [
            'loan_id' => $loan->id,
            'repayment_id' => $repayment->id,
        ]);
    }

    // ─── Get Total Paid for Repayment ──────────────────────────────────

    protected function getTotalPaidForRepayment(Repayment $repayment): float
    {
        // Sum from metadata payment history
        $history = $repayment->metadata['payment_history'] ?? [];
        
        return collect($history)->sum('amount');
    }

    // ─── Interest Portion ────────────────────────────────────────────
    // Delegated to the Financial Engine; kept here for compatibility.

    protected function calculateInterestPortion(Loan $loan): float
    {
        return $this->loanService->loanLenderReturnAmount($loan);
    }

    // ─── Queries ─────────────────────────────────────────────────────

    public function getRepayment(int $id): ?Repayment
    {
        return Repayment::with(['loan', 'borrower', 'lenderRepayments.lender'])->find($id);
    }

    public function getLoanRepayments(int $loanId)
    {
        return Repayment::forLoan($loanId)
            ->with(['lenderRepayments.lender'])
            ->orderBy('due_date')
            ->get();
    }

    public function getBorrowerRepayments(int $borrowerId, array $filters = [])
    {
        $query = Repayment::forBorrower($borrowerId)
            ->with('loan')
            ->orderBy('due_date', 'desc');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function getLenderRepayments(int $lenderId)
    {
        return LenderRepayment::forLender($lenderId)
            ->with(['repayment.loan', 'fundingTransaction'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
    }

    public function getLenderEarningsSummary(int $lenderId): array
    {
        $processed = LenderRepayment::forLender($lenderId)->processed();

        return [
            'total_repaid' => round((clone $processed)->sum('amount'), 2),
            'principal_returned' => round((clone $processed)->sum('principal_return'), 2),
            'interest_earned' => round((clone $processed)->sum('interest_earned'), 2),
            'penalty_share' => round((clone $processed)->sum('penalty_share'), 2),
            'repayments_count' => (clone $processed)->count(),
            'active_loans' => LenderRepayment::forLender($lenderId)
                ->whereHas('repayment', fn ($q) => $q->whereIn('status', ['pending', 'partial', 'overdue']))
                ->distinct('funding_transaction_id')
                ->count('funding_transaction_id'),
        ];
    }

    public function getOverdueSummary(): array
    {
        $overdue = Repayment::overdue();

        return [
            'total_overdue' => (clone $overdue)->count(),
            'total_amount' => round((clone $overdue)->sum('amount'), 2),
            'total_penalties' => round((clone $overdue)->sum('penalty'), 2),
            'avg_days_overdue' => round((clone $overdue)->avg('days_overdue') ?? 0, 1),
        ];
    }

    public function getUpcomingRepayments(int $days = 7)
    {
        return Repayment::upcoming($days)
            ->with(['borrower', 'loan'])
            ->get();
    }
}
