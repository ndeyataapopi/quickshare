<?php

namespace App\Modules\Notifications;

use App\Modules\Notifications\Events\NotificationSent;
use App\Modules\Notifications\Listeners\LogNotification;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class NotificationsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationService::class);
    }

    public function boot(): void
    {
        Event::listen(NotificationSent::class, LogNotification::class);
    }
}
