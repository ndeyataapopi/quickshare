<?php

namespace App\Modules\TrustScore;

use App\Modules\Auth\Events\UserLoggedIn;
use App\Modules\KYC\Events\KycApproved;
use App\Modules\Loans\Events\LoanDefaulted;
use App\Modules\Repayments\Events\LoanFullyRepaid;
use App\Modules\Repayments\Events\RepaymentMade;
use App\Modules\Repayments\Events\RepaymentOverdue;
use App\Modules\TrustScore\Events\TrustScoreCalculated;
use App\Modules\TrustScore\Listeners\BoostScoreOnKycApproval;
use App\Modules\TrustScore\Listeners\LogTrustScoreChange;
use App\Modules\TrustScore\Listeners\PenalizeScoreOnLoanDefault;
use App\Modules\TrustScore\Listeners\RecalculateScoreOnLogin;
use App\Modules\TrustScore\Listeners\RecalculateTrustScore;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TrustScoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Services\TrustScoreService::class);
    }

    public function boot(): void
    {
        // Cross-module: recalculate on repayment events
        Event::listen(RepaymentMade::class, RecalculateTrustScore::class);
        Event::listen(RepaymentOverdue::class, RecalculateTrustScore::class);
        Event::listen(LoanFullyRepaid::class, RecalculateTrustScore::class);

        // Cross-module: penalize on loan default
        Event::listen(LoanDefaulted::class, PenalizeScoreOnLoanDefault::class);

        // Cross-module: boost on KYC approval
        Event::listen(KycApproved::class, BoostScoreOnKycApproval::class);

        // Recalculate trust score on login so it's always up to date
        Event::listen(UserLoggedIn::class, RecalculateScoreOnLogin::class);

        // Log all trust score changes
        Event::listen(TrustScoreCalculated::class, LogTrustScoreChange::class);
    }
}
