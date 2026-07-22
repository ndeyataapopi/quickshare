<?php

namespace App\Modules\KYC;

use App\Modules\KYC\Events\KycApproved;
use App\Modules\KYC\Events\KycRejected;
use App\Modules\KYC\Events\KycResubmitted;
use App\Modules\KYC\Events\KycSubmitted;
use App\Modules\KYC\Listeners\LogKycApproved;
use App\Modules\KYC\Listeners\LogKycRejected;
use App\Modules\KYC\Listeners\LogKycResubmission;
use App\Modules\KYC\Listeners\LogKycSubmission;
use App\Modules\KYC\Listeners\NotifyAdminOfKycSubmission;
use App\Modules\KYC\Listeners\NotifyKycStatus;
use App\Modules\KYC\Listeners\UpdateUserVerificationStatus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class KYCServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen(KycSubmitted::class, LogKycSubmission::class);
        Event::listen(KycSubmitted::class, NotifyAdminOfKycSubmission::class);
        Event::listen(KycApproved::class, LogKycApproved::class);
        Event::listen(KycApproved::class, NotifyKycStatus::class);
        Event::listen(KycApproved::class, UpdateUserVerificationStatus::class);
        Event::listen(KycRejected::class, LogKycRejected::class);
        Event::listen(KycRejected::class, NotifyKycStatus::class);
        Event::listen(KycResubmitted::class, LogKycResubmission::class);
    }
}
