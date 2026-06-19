<?php

namespace App\Modules\Funding;

use App\Modules\Funding\Events\LoanFunded;
use App\Modules\Funding\Events\FundingCompleted;
use App\Modules\Funding\Listeners\LogFundingActivity;
use App\Modules\Funding\Listeners\NotifyBorrowerFunded;
use App\Modules\Funding\Listeners\TriggerLoanDisbursement;
use App\Modules\Funding\Services\FundingService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class FundingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FundingService::class);
    }

    public function boot(): void
    {
        Event::listen(LoanFunded::class, LogFundingActivity::class);
        Event::listen(LoanFunded::class, NotifyBorrowerFunded::class);
        Event::listen(FundingCompleted::class, TriggerLoanDisbursement::class);
    }
}
