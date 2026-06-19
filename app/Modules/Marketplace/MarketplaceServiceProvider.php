<?php

namespace App\Modules\Marketplace;

use App\Modules\Marketplace\Events\LoanListed;
use App\Modules\Marketplace\Events\LoanDelisted;
use App\Modules\Marketplace\Listeners\LogMarketplaceActivity;
use App\Modules\Marketplace\Listeners\NotifyLendersNewListing;
use App\Modules\Marketplace\Services\MarketplaceService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class MarketplaceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(MarketplaceService::class);
    }

    public function boot(): void
    {
        Event::listen(LoanListed::class, [LogMarketplaceActivity::class, NotifyLendersNewListing::class]);
        Event::listen(LoanDelisted::class, LogMarketplaceActivity::class);
    }
}
