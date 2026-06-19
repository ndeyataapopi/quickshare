<?php

namespace App\Modules\Repayments;

use App\Modules\Repayments\Events\RepaymentMade;
use App\Modules\Repayments\Events\RepaymentOverdue;
use App\Modules\Repayments\Events\LoanFullyRepaid;
use App\Modules\Repayments\Listeners\LogRepaymentActivity;
use App\Modules\Repayments\Listeners\NotifyRepaymentReceived;
use App\Modules\Repayments\Listeners\NotifyOverdueRepayment;
use App\Modules\Repayments\Listeners\UpdateLoanStatus;
use App\Modules\Repayments\Services\RepaymentService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class RepaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RepaymentService::class);
    }

    public function boot(): void
    {
        Event::listen(RepaymentMade::class, LogRepaymentActivity::class);
        Event::listen(RepaymentMade::class, NotifyRepaymentReceived::class);
        Event::listen(RepaymentOverdue::class, NotifyOverdueRepayment::class);
        Event::listen(LoanFullyRepaid::class, UpdateLoanStatus::class);
    }
}
