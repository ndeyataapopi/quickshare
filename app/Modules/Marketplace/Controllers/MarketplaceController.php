<?php

namespace App\Modules\Marketplace\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Loans\Services\TrustTierService;
use App\Modules\Marketplace\Services\MarketplaceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MarketplaceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected MarketplaceService $marketplaceService,
        protected TrustTierService $trustTierService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'risk' => ['sometimes', 'string', 'in:low,medium,high'],
            'trust_tier' => ['sometimes', 'string', Rule::in($this->trustTierService->names())],
            'amount_min' => ['sometimes', 'numeric', 'min:0'],
            'amount_max' => ['sometimes', 'numeric', 'min:0'],
            'term_min' => ['sometimes', 'integer', 'min:1'],
            'term_max' => ['sometimes', 'integer', 'min:1'],
            'search' => ['sometimes', 'string', 'max:50'],
            'sort' => ['sometimes', 'string', 'in:approved_amount,interest_rate,loan_term_days,trust_score,risk_score,funding_progress,approved_at'],
            'direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $listings = $this->marketplaceService->getListings($request->only([
            'risk', 'trust_tier', 'amount_min', 'amount_max',
            'term_min', 'term_max', 'search', 'sort', 'direction', 'per_page', 'page',
        ]));

        // Transform each listing for the lender view
        $transformed = collect($listings->items())->map(
            fn ($loan) => $this->marketplaceService->transformListing($loan),
        );

        return response()->json([
            'success' => true,
            'message' => 'Marketplace listings retrieved.',
            'data' => $transformed,
            'meta' => [
                'current_page' => $listings->currentPage(),
                'last_page' => $listings->lastPage(),
                'per_page' => $listings->perPage(),
                'total' => $listings->total(),
                'from' => $listings->firstItem(),
                'to' => $listings->lastItem(),
            ],
            'links' => [
                'first' => $listings->url(1),
                'last' => $listings->url($listings->lastPage()),
                'prev' => $listings->previousPageUrl(),
                'next' => $listings->nextPageUrl(),
            ],
        ]);
    }

    public function show(int $loan): JsonResponse
    {
        $listing = $this->marketplaceService->getListing($loan);

        if (! $listing) {
            return $this->notFound('Listing not found or no longer on marketplace.');
        }

        return $this->success(['listing' => $listing]);
    }

    public function stats(): JsonResponse
    {
        $stats = $this->marketplaceService->getStats();

        return $this->success($stats, 'Marketplace statistics.');
    }
}
