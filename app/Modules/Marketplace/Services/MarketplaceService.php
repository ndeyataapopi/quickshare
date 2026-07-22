<?php

namespace App\Modules\Marketplace\Services;

use App\Modules\Loans\Models\Loan;
use App\Modules\Loans\Services\LoanService;
use App\Modules\Loans\Services\TrustTierService;
use App\Modules\TrustScore\Services\TrustScoreService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

class MarketplaceService
{
    const CACHE_TTL = 300; // 5 minutes
    const CACHE_PREFIX = 'marketplace:';
    const PER_PAGE_DEFAULT = 15;
    const PER_PAGE_MAX = 50;

    public function __construct(
        protected TrustTierService $trustTierService,
        protected LoanService $loanService,
    ) {
    }

    // ─── Listing Query ───────────────────────────────────────────────

    public function getListings(array $filters = []): LengthAwarePaginator
    {
        $cacheKey = $this->buildCacheKey($filters);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($filters) {
            return $this->buildQuery($filters)->paginate(
                $this->getPerPage($filters),
            );
        });
    }

    // ─── Single Listing ──────────────────────────────────────────────

    public function getListing(int $loanId): ?array
    {
        $cacheKey = self::CACHE_PREFIX . "listing:{$loanId}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($loanId) {
            $loan = Loan::onMarketplace()
                ->with('borrower:id,first_name,trust_score')
                ->find($loanId);

            if (! $loan) {
                return null;
            }

            return $this->transformListing($loan);
        });
    }

    // ─── Statistics ──────────────────────────────────────────────────

    public function getStats(): array
    {
        return Cache::remember(self::CACHE_PREFIX . 'stats', self::CACHE_TTL, function () {
            $marketplace = Loan::onMarketplace();

            return [
                'total_listings' => (clone $marketplace)->count(),
                'active_funding' => (clone $marketplace)->where('loans.status', 'partially_funded')->count(),
                'total_value' => round((float) (clone $marketplace)->sum('approved_amount'), 2),
                'avg_interest_rate' => round((float) (clone $marketplace)->avg('interest_rate'), 2),
                'avg_trust_score' => round((float) (clone $marketplace)
                    ->join('users', 'loans.borrower_id', '=', 'users.id')
                    ->avg('users.trust_score'), 2),
                'fully_funded_today' => Loan::where('loans.status', 'funded')
                    ->whereDate('loans.updated_at', today())
                    ->count(),
            ];
        });
    }

    // ─── Cache Invalidation ──────────────────────────────────────────

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_PREFIX . 'stats');
        // Pattern-based flush for listing pages is handled by TTL expiry
    }

    public function clearListingCache(int $loanId): void
    {
        Cache::forget(self::CACHE_PREFIX . "listing:{$loanId}");
    }

    // ─── Query Builder ───────────────────────────────────────────────

    protected function buildQuery(array $filters = [])
    {
        $query = Loan::query()
            ->onMarketplace()
            ->select('loans.*')
            ->join('users', 'loans.borrower_id', '=', 'users.id');

        // ─── Filters ─────────────────────────────────────────────

        if (! empty($filters['risk'])) {
            $query = $this->applyRiskFilter($query, $filters['risk']);
        }

        if (! empty($filters['amount_min'])) {
            $query->where('loans.approved_amount', '>=', (float) $filters['amount_min']);
        }

        if (! empty($filters['amount_max'])) {
            $query->where('loans.approved_amount', '<=', (float) $filters['amount_max']);
        }

        if (! empty($filters['term_min'])) {
            $query->where('loans.loan_term_days', '>=', (int) $filters['term_min']);
        }

        if (! empty($filters['term_max'])) {
            $query->where('loans.loan_term_days', '<=', (int) $filters['term_max']);
        }

        if (! empty($filters['trust_tier'])) {
            $query = $this->applyTrustTierFilter($query, $filters['trust_tier']);
        }

        if (! empty($filters['search'])) {
            $query->where('loans.reference', 'like', '%' . $filters['search'] . '%');
        }

        // ─── Sorting ─────────────────────────────────────────────

        $sortField = $filters['sort'] ?? 'approved_at';
        $sortDir = strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $allowedSorts = [
            'approved_amount' => 'loans.approved_amount',
            'interest_rate' => 'loans.interest_rate',
            'loan_term_days' => 'loans.loan_term_days',
            'trust_score' => 'users.trust_score',
            'risk_score' => 'loans.risk_score',
            'funding_progress' => 'loans.funded_amount',
            'approved_at' => 'loans.approved_at',
        ];

        $column = $allowedSorts[$sortField] ?? 'loans.approved_at';
        $query->orderBy($column, $sortDir);

        // Eager load anonymized borrower data
        $query->with(['borrower' => function ($q) {
            $q->select('id', 'first_name', 'trust_score');
        }]);

        return $query;
    }

    // ─── Risk Filter ─────────────────────────────────────────────────

    protected function applyRiskFilter($query, string $risk)
    {
        $silver = $this->trustTierService->forName('silver');
        $gold = $this->trustTierService->forName('gold');

        return match ($risk) {
            'low' => $query->where('users.trust_score', '>=', $gold['trust_score']['min']),
            'medium' => $query->whereBetween('users.trust_score', [
                $silver['trust_score']['min'],
                $silver['trust_score']['max'],
            ]),
            'high' => $query->where('users.trust_score', '<', $silver['trust_score']['min']),
            default => $query,
        };
    }

    // ─── Trust Tier Filter ───────────────────────────────────────────

    protected function applyTrustTierFilter($query, string $tier)
    {
        $rule = $this->trustTierService->forName($tier);

        return $query->whereBetween('users.trust_score', [
            $rule['trust_score']['min'],
            $rule['trust_score']['max'],
        ]);
    }

    // ─── Transform for Lender View ───────────────────────────────────

    public function transformListing(Loan $loan): array
    {
        $borrower = $loan->borrower;
        $trustScore = (float) $borrower->trust_score;
        $approved = $this->loanService->loanPrincipal($loan);
        $funded = (float) $loan->funded_amount;
        $remaining = $this->loanService->remainingFunding($loan);
        $fundingProgress = $this->loanService->fundingProgress($loan);

        // QuickShare loans have one flat bullet repayment on the due date
        $repaymentSchedule = [];
        if ($loan->loan_term_days && $approved > 0) {
            $repaymentSchedule[] = [
                'installment' => 1,
                'due_date' => $loan->repayment_date?->toDateString()
                    ?? now()->addDays($loan->loan_term_days)->toDateString(),
                'amount' => (float) $loan->total_repayment,
            ];
        }

        $repayment = $this->loanService->repaymentCalculation($loan);
        $lenderExpectedReturn = $this->loanService->expectedReturnForFunding($loan, $approved);
        $lenderExpectedProfit = $this->loanService->expectedProfitForFunding($loan, $approved);
        $totalLoanCharge = round($repayment['amount'] - $repayment['principal'], 2);

        $fundingHistory = $loan->fundingTransactions()
            ->confirmed()
            ->latest('confirmed_at')
            ->get()
            ->map(function ($funding) {
                return [
                    'lender_hash' => substr(md5($funding->lender_id), 0, 8),
                    'amount' => (float) $funding->amount,
                    'confirmed_at' => $funding->confirmed_at?->toDateString(),
                ];
            });

        return [
            'id' => $loan->id,
            'reference' => $loan->reference,
            'borrower' => [
                'name' => trim($borrower->first_name . ' ' . $borrower->last_name),
                'id_hash' => substr(md5($borrower->id), 0, 8),
                'trust_score' => $trustScore,
                'trust_tier' => TrustScoreService::getTier($trustScore),
                'risk_level' => TrustScoreService::riskLevel($borrower),
                'repayment_probability' => $this->calculateRepaymentProbability($trustScore, $loan),
            ],
            'loan' => [
                'approved_amount' => $approved,
                'purpose' => $loan->purpose,
                'interest_rate' => (float) $loan->interest_rate,
                'platform_fee' => (float) $loan->platform_fee,
                'total_repayment' => (float) $loan->total_repayment,
                'total_loan_charge' => $totalLoanCharge,
                'flat_fee' => $totalLoanCharge,
                'lender_return' => $repayment['lender_return'],
                'borrower_repayment' => $repayment['amount'],
                'expected_return' => $lenderExpectedReturn,
                'expected_profit' => $lenderExpectedProfit,
                'loan_term_days' => $loan->loan_term_days,
                'repayment_date' => $loan->repayment_date?->toDateString(),
                'repayment_schedule' => $repaymentSchedule,
                'risk_score' => (float) $loan->risk_score,
            ],
            'funding' => [
                'funded_amount' => $funded,
                'remaining_amount' => $remaining,
                'progress_percent' => $fundingProgress,
                'status' => $loan->status,
            ],
            'funding_history' => $fundingHistory,
            'listed_at' => $loan->approved_at?->toIso8601String(),
        ];
    }

    // ─── Repayment Probability ───────────────────────────────────────

    protected function calculateRepaymentProbability(float $trustScore, Loan $loan): float
    {
        // Base probability from trust score (normalized 0-1)
        $base = $trustScore / 100;

        // Adjust for loan term (longer = slightly lower probability)
        $termFactor = max(0.85, 1 - ($loan->loan_term_days / 365 * 0.15));

        // Adjust for loan amount relative to tier limit
        $tierLimit = TrustScoreService::maxLoanAmount($loan->borrower);
        $amountRatio = $tierLimit > 0
            ? (float) ($loan->approved_amount ?? $loan->requested_amount) / $tierLimit
            : 1;
        $amountFactor = max(0.8, 1 - ($amountRatio * 0.2));

        $probability = $base * $termFactor * $amountFactor;

        return round(max(0, min(100, $probability * 100)), 1);
    }

    // ─── Helpers ─────────────────────────────────────────────────────

    protected function getPerPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? self::PER_PAGE_DEFAULT);

        return min(max(1, $perPage), self::PER_PAGE_MAX);
    }

    protected function buildCacheKey(array $filters): string
    {
        $normalized = $filters;
        ksort($normalized);

        return self::CACHE_PREFIX . 'listings:' . md5(serialize($normalized));
    }
}
