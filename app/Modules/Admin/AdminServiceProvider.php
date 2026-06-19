<?php

namespace App\Modules\Admin;

use App\Modules\Admin\Events\FraudAlert;
use App\Modules\Admin\Listeners\NotifyFraudAlert;
use App\Modules\Admin\Services\AdminDashboardService;
use App\Modules\Admin\Services\FraudDetectionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminDashboardService::class);
        $this->app->singleton(FraudDetectionService::class);
    }

    public function boot(): void
    {
        Event::listen(FraudAlert::class, NotifyFraudAlert::class);
    }
}
