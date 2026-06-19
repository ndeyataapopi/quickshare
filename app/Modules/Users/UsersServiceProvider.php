<?php

namespace App\Modules\Users;

use App\Modules\Users\Events\UserProfileUpdated;
use App\Modules\Users\Events\UserDeactivated;
use App\Modules\Users\Listeners\LogProfileUpdate;
use App\Modules\Users\Listeners\NotifyUserDeactivation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(UserProfileUpdated::class, LogProfileUpdate::class);
        Event::listen(UserDeactivated::class, NotifyUserDeactivation::class);
    }
}
