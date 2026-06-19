<?php

namespace App\Modules\Loans\Policies;

use App\Models\User;
use App\Modules\Loans\Models\Loan;

class LoanPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view_own_loans')
            || $user->hasPermissionTo('manage_loans');
    }

    public function view(User $user, Loan $loan): bool
    {
        if ($user->hasPermissionTo('manage_loans')) {
            return true;
        }

        return $loan->borrower_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('request_loan') && $user->canBorrow();
    }

    public function cancel(User $user, Loan $loan): bool
    {
        return $loan->borrower_id === $user->id && $loan->isCancellable();
    }

    public function approve(User $user, Loan $loan): bool
    {
        return $user->hasPermissionTo('manage_loans') && $loan->isApprovable();
    }

    public function reject(User $user, Loan $loan): bool
    {
        return $user->hasPermissionTo('manage_loans') && $loan->isApprovable();
    }
}
