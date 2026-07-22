<?php

namespace Tests\Feature\Marketplace;

use App\Models\User;
use App\Modules\Loans\Models\Loan;
use App\Modules\Marketplace\Services\MarketplaceService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    use RefreshDatabase;

    protected User $lender;
    protected User $borrower;
    protected MarketplaceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->service = app(MarketplaceService::class);

        $this->lender = User::factory()->active()->create(['trust_score' => 70.00]);
        $this->assignClientRole($this->lender);

        $this->borrower = User::factory()->active()->create(['trust_score' => 65.00]);
        $this->assignClientRole($this->borrower);
    }

    protected function createMarketplaceLoan(array $overrides = []): Loan
    {
        $borrower = $overrides['borrower'] ?? $this->borrower;
        unset($overrides['borrower']);

        return Loan::create(array_merge([
            'borrower_id' => $borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 10000,
            'approved_amount' => 10000,
            'interest_rate' => 15.00,
            'platform_fee' => 300,
            'total_repayment' => 10546,
            'funded_amount' => 0,
            'loan_term_days' => 60,
            'repayment_date' => now()->addDays(60)->toDateString(),
            'status' => 'marketplace',
            'risk_score' => 65.00,
            'submitted_at' => now(),
            'approved_at' => now(),
        ], $overrides));
    }

    // ─── Listing Query Tests ─────────────────────────────────────────

    public function test_listings_only_include_marketplace_loans(): void
    {
        $this->createMarketplaceLoan();
        Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 30,
            'status' => 'active', // not on marketplace
            'submitted_at' => now(),
        ]);

        $result = $this->service->getListings();

        $this->assertEquals(1, $result->total());
    }

    public function test_partially_funded_loans_are_included(): void
    {
        $this->createMarketplaceLoan(['status' => 'partially_funded', 'funded_amount' => 3000]);

        $result = $this->service->getListings();

        $this->assertEquals(1, $result->total());
    }

    public function test_expired_loans_are_excluded(): void
    {
        $this->createMarketplaceLoan(['repayment_date' => now()->subDay()->toDateString()]);
        $this->createMarketplaceLoan();

        $result = $this->service->getListings();

        $this->assertEquals(1, $result->total());
    }

    public function test_expired_loan_single_listing_returns_null(): void
    {
        $loan = $this->createMarketplaceLoan(['repayment_date' => now()->subDay()->toDateString()]);

        $listing = $this->service->getListing($loan->id);

        $this->assertNull($listing);
    }

    public function test_stats_exclude_expired_loans(): void
    {
        $this->createMarketplaceLoan(['approved_amount' => 10000, 'repayment_date' => now()->subDay()->toDateString()]);
        $this->createMarketplaceLoan(['approved_amount' => 20000]);

        $stats = $this->service->getStats();

        $this->assertEquals(1, $stats['total_listings']);
        $this->assertEquals(20000.00, $stats['total_value']);
    }

    // ─── Filter Tests ────────────────────────────────────────────────

    public function test_filter_by_amount_range(): void
    {
        $this->createMarketplaceLoan(['approved_amount' => 5000]);
        $this->createMarketplaceLoan(['approved_amount' => 15000]);
        $this->createMarketplaceLoan(['approved_amount' => 25000]);

        $result = $this->service->getListings(['amount_min' => 10000, 'amount_max' => 20000]);

        $this->assertEquals(1, $result->total());
    }

    public function test_filter_by_term_range(): void
    {
        $this->createMarketplaceLoan(['loan_term_days' => 30]);
        $this->createMarketplaceLoan(['loan_term_days' => 90]);
        $this->createMarketplaceLoan(['loan_term_days' => 180]);

        $result = $this->service->getListings(['term_min' => 60, 'term_max' => 120]);

        $this->assertEquals(1, $result->total());
    }

    public function test_filter_by_risk_level(): void
    {
        $highTrust = User::factory()->active()->create(['trust_score' => 80.00]);
        $this->assignClientRole($highTrust);
        $lowTrust = User::factory()->active()->create(['trust_score' => 35.00]);
        $this->assignClientRole($lowTrust);

        $this->createMarketplaceLoan(['borrower' => $highTrust]);
        $this->createMarketplaceLoan(['borrower' => $lowTrust]);

        $lowRisk = $this->service->getListings(['risk' => 'low']);
        $this->assertEquals(1, $lowRisk->total());

        $highRisk = $this->service->getListings(['risk' => 'high']);
        $this->assertEquals(1, $highRisk->total());
    }

    public function test_filter_by_trust_tier(): void
    {
        $goldUser = User::factory()->active()->create(['trust_score' => 75.00]);
        $this->assignClientRole($goldUser);
        $silverUser = User::factory()->active()->create(['trust_score' => 55.00]);
        $this->assignClientRole($silverUser);

        $this->createMarketplaceLoan(['borrower' => $goldUser]);
        $this->createMarketplaceLoan(['borrower' => $silverUser]);

        $gold = $this->service->getListings(['trust_tier' => 'gold']);
        $this->assertEquals(1, $gold->total());

        $silver = $this->service->getListings(['trust_tier' => 'silver']);
        $this->assertEquals(1, $silver->total());
    }

    public function test_search_by_reference(): void
    {
        $loan = $this->createMarketplaceLoan();
        $this->createMarketplaceLoan();

        $result = $this->service->getListings(['search' => $loan->reference]);

        $this->assertEquals(1, $result->total());
    }

    // ─── Sorting Tests ───────────────────────────────────────────────

    public function test_sort_by_amount_ascending(): void
    {
        $this->createMarketplaceLoan(['approved_amount' => 20000]);
        $this->createMarketplaceLoan(['approved_amount' => 5000]);
        $this->createMarketplaceLoan(['approved_amount' => 10000]);

        $result = $this->service->getListings(['sort' => 'approved_amount', 'direction' => 'asc']);
        $amounts = collect($result->items())->pluck('approved_amount')->map(fn ($v) => (float) $v)->toArray();

        $this->assertEquals([5000.00, 10000.00, 20000.00], $amounts);
    }

    public function test_sort_by_trust_score_descending(): void
    {
        $low = User::factory()->active()->create(['trust_score' => 40.00]);
        $this->assignClientRole($low);
        $high = User::factory()->active()->create(['trust_score' => 80.00]);
        $this->assignClientRole($high);

        $this->createMarketplaceLoan(['borrower' => $low]);
        $this->createMarketplaceLoan(['borrower' => $high]);

        $result = $this->service->getListings(['sort' => 'trust_score', 'direction' => 'desc']);
        $items = $result->items();

        $this->assertEquals($high->id, $items[0]->borrower_id);
        $this->assertEquals($low->id, $items[1]->borrower_id);
    }

    public function test_default_sort_is_approved_at_desc(): void
    {
        $older = $this->createMarketplaceLoan(['approved_at' => now()->subDay()]);
        $newer = $this->createMarketplaceLoan(['approved_at' => now()]);

        $result = $this->service->getListings();
        $items = $result->items();

        $this->assertEquals($newer->id, $items[0]->id);
    }

    // ─── Pagination Tests ────────────────────────────────────────────

    public function test_pagination_defaults_to_15_per_page(): void
    {
        foreach (range(1, 20) as $_) {
            $this->createMarketplaceLoan();
        }

        $result = $this->service->getListings();

        $this->assertEquals(15, $result->perPage());
        $this->assertEquals(20, $result->total());
        $this->assertCount(15, $result->items());
    }

    public function test_custom_per_page(): void
    {
        foreach (range(1, 10) as $_) {
            $this->createMarketplaceLoan();
        }

        $result = $this->service->getListings(['per_page' => 5]);

        $this->assertEquals(5, $result->perPage());
        $this->assertCount(5, $result->items());
    }

    public function test_per_page_capped_at_50(): void
    {
        $this->createMarketplaceLoan();

        $result = $this->service->getListings(['per_page' => 100]);

        $this->assertEquals(50, $result->perPage());
    }

    // ─── Single Listing Tests ────────────────────────────────────────

    public function test_get_single_listing_returns_transformed_data(): void
    {
        $loan = $this->createMarketplaceLoan();

        $listing = $this->service->getListing($loan->id);

        $this->assertNotNull($listing);
        $this->assertEquals($loan->reference, $listing['reference']);

        // Borrower data is anonymized
        $this->assertArrayHasKey('id_hash', $listing['borrower']);
        $this->assertArrayHasKey('trust_score', $listing['borrower']);
        $this->assertArrayHasKey('trust_tier', $listing['borrower']);
        $this->assertArrayHasKey('risk_level', $listing['borrower']);
        $this->assertArrayHasKey('repayment_probability', $listing['borrower']);
        $this->assertArrayNotHasKey('email', $listing['borrower']);
        $this->assertArrayNotHasKey('last_name', $listing['borrower']);
        $this->assertArrayNotHasKey('phone', $listing['borrower']);

        // Loan data
        $this->assertArrayHasKey('approved_amount', $listing['loan']);
        $this->assertArrayHasKey('interest_rate', $listing['loan']);
        $this->assertArrayHasKey('loan_term_days', $listing['loan']);

        // Funding progress
        $this->assertArrayHasKey('funded_amount', $listing['funding']);
        $this->assertArrayHasKey('remaining_amount', $listing['funding']);
        $this->assertArrayHasKey('progress_percent', $listing['funding']);
    }

    public function test_non_marketplace_loan_returns_null(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 30,
            'status' => 'active',
            'submitted_at' => now(),
        ]);

        $listing = $this->service->getListing($loan->id);

        $this->assertNull($listing);
    }

    // ─── Funding Progress Tests ──────────────────────────────────────

    public function test_funding_progress_calculation(): void
    {
        $loan = $this->createMarketplaceLoan([
            'approved_amount' => 10000,
            'funded_amount' => 4000,
            'status' => 'partially_funded',
        ]);

        $listing = $this->service->getListing($loan->id);

        $this->assertEquals(4000.00, $listing['funding']['funded_amount']);
        $this->assertEquals(6000.00, $listing['funding']['remaining_amount']);
        $this->assertEquals(40.00, $listing['funding']['progress_percent']);
    }

    // ─── Repayment Probability Tests ─────────────────────────────────

    public function test_repayment_probability_is_between_0_and_100(): void
    {
        $loan = $this->createMarketplaceLoan();

        $listing = $this->service->getListing($loan->id);

        $this->assertGreaterThanOrEqual(0, $listing['borrower']['repayment_probability']);
        $this->assertLessThanOrEqual(100, $listing['borrower']['repayment_probability']);
    }

    public function test_higher_trust_score_gives_higher_probability(): void
    {
        $highTrust = User::factory()->active()->create(['trust_score' => 90.00]);
        $this->assignClientRole($highTrust);
        $lowTrust = User::factory()->active()->create(['trust_score' => 35.00]);
        $this->assignClientRole($lowTrust);

        $loan1 = $this->createMarketplaceLoan(['borrower' => $highTrust]);
        $loan2 = $this->createMarketplaceLoan(['borrower' => $lowTrust]);

        $listing1 = $this->service->getListing($loan1->id);
        $listing2 = $this->service->getListing($loan2->id);

        $this->assertGreaterThan(
            $listing2['borrower']['repayment_probability'],
            $listing1['borrower']['repayment_probability'],
        );
    }

    // ─── Anonymization Tests ─────────────────────────────────────────

    public function test_borrower_identity_is_anonymized(): void
    {
        $loan = $this->createMarketplaceLoan();
        $listing = $this->service->getListing($loan->id);

        // Hash should be 8 chars, not the actual ID
        $this->assertEquals(8, strlen($listing['borrower']['id_hash']));
        $this->assertNotEquals($this->borrower->id, $listing['borrower']['id_hash']);
    }

    // ─── Caching Tests ───────────────────────────────────────────────

    public function test_listings_are_cached(): void
    {
        $this->createMarketplaceLoan();

        // First call hits DB
        $result1 = $this->service->getListings();

        // Create another loan — cached result should still show 1
        $this->createMarketplaceLoan();
        $result2 = $this->service->getListings();

        $this->assertEquals($result1->total(), $result2->total());
    }

    public function test_cache_clear_refreshes_data(): void
    {
        $this->createMarketplaceLoan();
        $this->service->getListings(); // populate cache

        $this->createMarketplaceLoan();
        $this->service->clearCache();
        Cache::flush(); // flush all for test

        $result = $this->service->getListings();
        $this->assertEquals(2, $result->total());
    }

    public function test_different_filters_produce_different_cache_keys(): void
    {
        $this->createMarketplaceLoan(['approved_amount' => 5000]);
        $this->createMarketplaceLoan(['approved_amount' => 15000]);

        $all = $this->service->getListings();
        $filtered = $this->service->getListings(['amount_min' => 10000]);

        $this->assertEquals(2, $all->total());
        $this->assertEquals(1, $filtered->total());
    }

    // ─── Statistics Tests ────────────────────────────────────────────

    public function test_stats_returns_marketplace_overview(): void
    {
        $this->createMarketplaceLoan(['approved_amount' => 10000]);
        $this->createMarketplaceLoan(['approved_amount' => 20000]);

        $stats = $this->service->getStats();

        $this->assertEquals(2, $stats['total_listings']);
        $this->assertEquals(30000.00, $stats['total_value']);
        $this->assertArrayHasKey('avg_interest_rate', $stats);
        $this->assertArrayHasKey('avg_trust_score', $stats);
        $this->assertArrayHasKey('fully_funded_today', $stats);
    }

    // ─── API Endpoint Tests ──────────────────────────────────────────

    public function test_lender_can_browse_marketplace(): void
    {
        $this->createMarketplaceLoan();
        $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);

        $response = $this->getJson('/api/marketplace');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'reference',
                        'borrower' => ['id_hash', 'trust_score', 'trust_tier', 'risk_level', 'repayment_probability'],
                        'loan' => ['approved_amount', 'interest_rate', 'loan_term_days'],
                        'funding' => ['funded_amount', 'remaining_amount', 'progress_percent'],
                    ],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_lender_can_filter_marketplace(): void
    {
        $this->createMarketplaceLoan(['approved_amount' => 5000]);
        $this->createMarketplaceLoan(['approved_amount' => 20000]);

        Sanctum::actingAs($this->lender);

        $response = $this->getJson('/api/marketplace?amount_min=10000');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_lender_can_sort_marketplace(): void
    {
        $this->createMarketplaceLoan(['approved_amount' => 20000]);
        $this->createMarketplaceLoan(['approved_amount' => 5000]);

        Sanctum::actingAs($this->lender);

        $response = $this->getJson('/api/marketplace?sort=approved_amount&direction=asc');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertLessThan($data[1]['loan']['approved_amount'], $data[0]['loan']['approved_amount']);
    }

    public function test_lender_can_paginate(): void
    {
        foreach (range(1, 5) as $_) {
            $this->createMarketplaceLoan();
        }

        Sanctum::actingAs($this->lender);

        $response = $this->getJson('/api/marketplace?per_page=2');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonCount(2, 'data');
    }

    public function test_lender_can_view_single_listing(): void
    {
        $loan = $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);

        $response = $this->getJson("/api/marketplace/{$loan->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.listing.reference', $loan->reference)
            ->assertJsonStructure([
                'data' => [
                    'listing' => [
                        'borrower' => ['id_hash', 'trust_score', 'trust_tier', 'repayment_probability'],
                        'loan', 'funding', 'listed_at',
                    ],
                ],
            ]);
    }

    public function test_non_marketplace_loan_returns_404(): void
    {
        $loan = Loan::create([
            'borrower_id' => $this->borrower->id,
            'reference' => Loan::generateReference(),
            'requested_amount' => 5000,
            'interest_rate' => 15,
            'platform_fee' => 150,
            'total_repayment' => 5273,
            'loan_term_days' => 30,
            'status' => 'active',
            'submitted_at' => now(),
        ]);

        Sanctum::actingAs($this->lender);

        $this->getJson("/api/marketplace/{$loan->id}")->assertStatus(404);
    }

    public function test_lender_can_view_stats(): void
    {
        $this->createMarketplaceLoan();

        Sanctum::actingAs($this->lender);

        $response = $this->getJson('/api/marketplace/stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['total_listings', 'total_value', 'avg_interest_rate', 'avg_trust_score'],
            ]);
    }

    public function test_invalid_filter_returns_422(): void
    {
        Sanctum::actingAs($this->lender);

        $this->getJson('/api/marketplace?risk=invalid')->assertStatus(422);
        $this->getJson('/api/marketplace?trust_tier=diamond')->assertStatus(422);
        $this->getJson('/api/marketplace?sort=invalid_field')->assertStatus(422);
    }

    // ─── RBAC Tests ──────────────────────────────────────────────────

    public function test_client_can_access_marketplace(): void
    {
        Sanctum::actingAs($this->borrower);

        $this->getJson('/api/marketplace')->assertStatus(200);
    }

    public function test_admin_can_access_marketplace(): void
    {
        $admin = User::factory()->active()->create();
        $this->assignAdminRole($admin);

        Sanctum::actingAs($admin);

        $this->getJson('/api/marketplace')->assertStatus(200);
    }

    public function test_unauthenticated_cannot_access_marketplace(): void
    {
        $this->getJson('/api/marketplace')->assertStatus(401);
    }
}
