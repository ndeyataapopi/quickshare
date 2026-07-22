<?php

namespace App\Modules\Funding;

use App\Modules\Funding\Events\FundingCompleted;
use App\Modules\Funding\Events\FundingInitiated;
use App\Modules\Funding\Events\FundingPaymentApproved;
use App\Modules\Funding\Events\FundingPaymentRejected;
use App\Modules\Funding\Events\FundingPaymentSubmitted;
use App\Modules\Funding\Events\InvestmentCreated;
use App\Modules\Funding\Events\LoanFunded;
use App\Modules\Funding\Listeners\LogFundingActivity;
use App\Modules\Funding\Listeners\LogFundingCompleted;
use App\Modules\Funding\Listeners\LogFundingInitiated;
use App\Modules\Funding\Listeners\LogFundingPaymentApproved;
use App\Modules\Funding\Listeners\LogFundingPaymentRejected;
use App\Modules\Funding\Listeners\LogFundingPaymentSubmitted;
use App\Modules\Funding\Listeners\LogInvestmentCreated;
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
        Event::listen(FundingCompleted::class, LogFundingCompleted::class);
        Event::listen(FundingCompleted::class, TriggerLoanDisbursement::class);
        Event::listen(FundingInitiated::class, LogFundingInitiated::class);
        Event::listen(FundingPaymentSubmitted::class, LogFundingPaymentSubmitted::class);
        Event::listen(FundingPaymentApproved::class, LogFundingPaymentApproved::class);
        Event::listen(FundingPaymentRejected::class, LogFundingPaymentRejected::class);
        Event::listen(InvestmentCreated::class, LogInvestmentCreated::class);
    }
}
