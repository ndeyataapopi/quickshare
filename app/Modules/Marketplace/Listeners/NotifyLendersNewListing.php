<?php

namespace App\Modules\Marketplace\Listeners;

use App\Modules\Marketplace\Events\LoanListed;
use Illuminate\Contracts\Queue\ShouldQueue;

class NotifyLendersNewListing implements ShouldQueue
{
    public function handle(LoanListed $event): void
    {
        // TODO: Notify eligible lenders about new marketplace listing
    }
}
