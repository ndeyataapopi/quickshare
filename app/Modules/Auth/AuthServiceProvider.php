<?php

namespace App\Modules\Auth;

use App\Modules\Auth\Events\UserLoggedIn;
use App\Modules\Auth\Events\UserRegistered;
use App\Modules\Auth\Listeners\LogSuccessfulLogin;
use App\Modules\Auth\Listeners\SendWelcomeNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(UserRegistered::class, SendWelcomeNotification::class);
        Event::listen(UserLoggedIn::class, LogSuccessfulLogin::class);
    }
}
