<?php

namespace App\Modules\Auth;

use App\Modules\Auth\Events\UserLoggedIn;
use App\Modules\Auth\Events\UserLoggedOut;
use App\Modules\Auth\Events\UserRegistered;
use App\Modules\Auth\Listeners\LogEmailVerified;
use App\Modules\Auth\Listeners\LogPasswordReset;
use App\Modules\Auth\Listeners\LogSuccessfulLogin;
use App\Modules\Auth\Listeners\LogUserLogout;
use App\Modules\Auth\Listeners\LogUserRegistered;
use App\Modules\Auth\Listeners\SendWelcomeNotification;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
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
        Event::listen(UserRegistered::class, LogUserRegistered::class);
        Event::listen(UserLoggedIn::class, LogSuccessfulLogin::class);
        Event::listen(UserLoggedOut::class, LogUserLogout::class);
        Event::listen(PasswordReset::class, LogPasswordReset::class);
        Event::listen(Verified::class, LogEmailVerified::class);
    }
}
