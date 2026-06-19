<?php

namespace App\Modules\Users\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->hasRole('admin');
    }

    public function update(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->hasRole('admin');
    }

    public function delete(User $user, User $target): bool
    {
        return $user->hasRole('admin') && $user->id !== $target->id;
    }

    public function suspend(User $user, User $target): bool
    {
        return $user->hasRole('admin') && $user->id !== $target->id;
    }

    public function viewReferrals(User $user, User $target): bool
    {
        return $user->id === $target->id || $user->hasRole('admin');
    }
}
