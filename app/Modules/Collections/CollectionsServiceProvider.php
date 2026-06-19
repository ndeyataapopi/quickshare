<?php

namespace App\Modules\Collections;

use App\Modules\Collections\Services\CollectionService;
use Illuminate\Support\ServiceProvider;

class CollectionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CollectionService::class);
    }

    public function boot(): void
    {
        // Collections module doesn't have event listeners by default
        // Events are triggered by RepaymentOverdue and other cross-module events
    }
}
