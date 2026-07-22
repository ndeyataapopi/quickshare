<?php

namespace App\Modules\Admin\Services;

use App\Modules\Funding\Models\FundingTransaction;
use App\Modules\KYC\Models\KycSubmission;
use App\Modules\Loans\Models\DisbursementTransaction;
use App\Modules\Loans\Models\Loan;
use App\Modules\Repayments\Models\LenderRepayment;
use App\Modules\Repayments\Models\Repayment;
use Illuminate\Support\Facades\DB;

class OperationsDashboardService
{
    // ─── Today's Loans ────────────────────────────────────────────────

    public function getTodaysLoans(): array
    {
        return [
            'submitted_today' => Loan::whereDate('submitted_at', today())->count(),
            'pending_review' => Loan::pendingReview()->count(),
            'approved_today' => Loan::whereDate('approved_at', today())->count(),
            'rejected_today' => Loan::whereDate('rejected_at', today())->count(),
        ];
    }

    // ─── Pending KYC ──────────────────────────────────────────────────

    public function getPendingKyc(): array
    {
        $oldest = KycSubmission::reviewable()
            ->oldest('submitted_at')
            ->first();

        return [
            'pending_verification' => KycSubmission::pending()->count(),
            'resubmissions' => KycSubmission::where('status', 'resubmission_required')->count(),
            'oldest_pending' => $oldest?->submitted_at?->diffForHumans() ?? null,
            'view_route' => route('admin.kyc.index'),
        ];
    }

    // ─── Loans Awaiting Approval ──────────────────────────────────────

    public function getLoansAwaitingApproval(): array
    {
        $oldest = Loan::pendingReview()
            ->oldest('submitted_at')
            ->first();

        $highValueThreshold = (float) config('loan.operations.high_value_threshold', 50000);

        return [
            'pending_count' => Loan::pendingReview()->count(),
            'oldest_waiting' => $oldest?->submitted_at?->diffForHumans() ?? null,
            'high_value_count' => Loan::pendingReview()
                ->where('requested_amount', '>=', $highValueThreshold)
                ->count(),
            'view_route' => route('admin.loans.index'),
        ];
    }

    // ─── Funding Awaiting Approval ────────────────────────────────────

    public function getFundingAwaitingApproval(): array
    {
        $oldest = FundingTransaction::where('status', 'pending')
            ->oldest()
            ->first();

        return [
            'pending_proofs' => FundingTransaction::where('status', 'pending')->count(),
            'oldest_waiting' => $oldest?->created_at?->diffForHumans() ?? null,
            'total_amount' => (float) FundingTransaction::where('status', 'pending')->sum('amount'),
            'view_route' => route('admin.funding-payments.index'),
        ];
    }

    // ─── Borrower Disbursements Awaiting Processing ───────────────────

    public function getDisbursementsAwaitingProcessing(): array
    {
        $oldest = Loan::whereIn('status', ['funded', 'awaiting_disbursement'])
            ->oldest('updated_at')
            ->first();

        return [
            'count' => Loan::whereIn('status', ['funded', 'awaiting_disbursement'])->count(),
            'total_amount' => (float) Loan::whereIn('status', ['funded', 'awaiting_disbursement'])
                ->sum('funded_amount'),
            'oldest_waiting' => $oldest?->updated_at?->diffForHumans() ?? null,
            'view_route' => route('admin.disbursements.index'),
        ];
    }

    // ─── Borrower Confirmations Awaiting ──────────────────────────────

    public function getBorrowerConfirmationsAwaiting(): array
    {
        $oldest = DisbursementTransaction::pendingBorrowerConfirmation()
            ->oldest()
            ->first();

        return [
            'count' => DisbursementTransaction::pendingBorrowerConfirmation()->count(),
            'oldest_waiting' => $oldest?->created_at?->diffForHumans() ?? null,
            'view_route' => route('admin.disbursements.index'),
        ];
    }

    // ─── Repayments Awaiting Approval ─────────────────────────────────

    public function getRepaymentsAwaitingApproval(): array
    {
        $oldest = Repayment::pendingApproval()
            ->oldest('updated_at')
            ->first();

        return [
            'count' => Repayment::pendingApproval()->count(),
            'total_amount' => (float) Repayment::pendingApproval()->sum('amount'),
            'oldest_waiting' => $oldest?->updated_at?->diffForHumans() ?? null,
            'view_route' => route('admin.repayments.index'),
        ];
    }

    // ─── Lender Payouts Awaiting ──────────────────────────────────────

    public function getLenderPayoutsAwaiting(): array
    {
        $oldest = LenderRepayment::processed()
            ->oldest('processed_at')
            ->first();

        return [
            'lenders_waiting' => LenderRepayment::processed()
                ->distinct('lender_id')
                ->count('lender_id'),
            'total_amount' => (float) LenderRepayment::processed()->sum('amount'),
            'oldest_payout' => $oldest?->processed_at?->diffForHumans() ?? null,
            'view_route' => route('admin.repayments.index'),
        ];
    }

    // ─── Failed Jobs ──────────────────────────────────────────────────

    public function getFailedJobs(): array
    {
        $failedTable = config('queue.failed.table', 'failed_jobs');

        try {
            $count = DB::table($failedTable)->count();
            $latest = DB::table($failedTable)->latest('failed_at')->first();
        } catch (\Throwable $e) {
            $count = 0;
            $latest = null;
        }

        return [
            'count' => $count,
            'latest_failure' => $latest?->failed_at
                ? \Carbon\Carbon::parse($latest->failed_at)->diffForHumans()
                : null,
            'retry_route' => route('admin.system-status.retry-failed'),
            'view_route' => route('admin.system-status.index'),
        ];
    }

    // ─── System Alerts ────────────────────────────────────────────────

    public function getSystemAlerts(): array
    {
        $alerts = [];

        $overdueLoans = Loan::where('status', 'overdue')->count();
        if ($overdueLoans > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'mdi-alert-circle',
                'message' => "{$overdueLoans} loan(s) overdue",
                'route' => route('admin.loans.index', ['status' => 'overdue']),
            ];
        }

        $overdueRepayments = Repayment::overdue()->count();
        if ($overdueRepayments > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'mdi-clock-alert',
                'message' => "{$overdueRepayments} repayment(s) overdue",
                'route' => route('admin.repayments.index'),
            ];
        }

        $failedTable = config('queue.failed.table', 'failed_jobs');
        try {
            $failedNotifications = DB::table($failedTable)
                ->whereIn('queue', ['notifications', 'emails'])
                ->count();
            if ($failedNotifications > 0) {
                $alerts[] = [
                    'type' => 'warning',
                    'icon' => 'mdi-email-alert',
                    'message' => "{$failedNotifications} failed notification/email job(s)",
                    'route' => route('admin.system-status.index'),
                ];
            }
        } catch (\Throwable $e) {
        }

        $stuckLoans = Loan::where('status', 'awaiting_disbursement')
            ->where('updated_at', '<', now()->subDays(2))
            ->count();
        if ($stuckLoans > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'mdi-timer-sand',
                'message' => "{$stuckLoans} loan(s) stuck in disbursement workflow (2+ days)",
                'route' => route('admin.disbursements.index'),
            ];
        }

        $failedDisbursements = DisbursementTransaction::failed()->count();
        if ($failedDisbursements > 0) {
            $alerts[] = [
                'type' => 'danger',
                'icon' => 'mdi-close-circle',
                'message' => "{$failedDisbursements} failed disbursement(s) need retry",
                'route' => route('admin.disbursements.index'),
            ];
        }

        $rejectedByBorrower = DisbursementTransaction::rejectedByBorrower()->count();
        if ($rejectedByBorrower > 0) {
            $alerts[] = [
                'type' => 'warning',
                'icon' => 'mdi-account-remove',
                'message' => "{$rejectedByBorrower} disbursement(s) rejected by borrower",
                'route' => route('admin.disbursements.index'),
            ];
        }

        return $alerts;
    }

    // ─── Full Operations Dashboard Data ───────────────────────────────

    public function getOperationsData(): array
    {
        return [
            'todays_loans' => $this->getTodaysLoans(),
            'pending_kyc' => $this->getPendingKyc(),
            'loans_awaiting_approval' => $this->getLoansAwaitingApproval(),
            'funding_awaiting_approval' => $this->getFundingAwaitingApproval(),
            'disbursements_awaiting' => $this->getDisbursementsAwaitingProcessing(),
            'borrower_confirmations' => $this->getBorrowerConfirmationsAwaiting(),
            'repayments_awaiting' => $this->getRepaymentsAwaitingApproval(),
            'lender_payouts' => $this->getLenderPayoutsAwaiting(),
            'failed_jobs' => $this->getFailedJobs(),
            'system_alerts' => $this->getSystemAlerts(),
        ];
    }
}
