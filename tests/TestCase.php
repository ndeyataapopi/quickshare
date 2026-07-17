<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function assignClientRole(User $user): User
    {
        $user->assignRole(UserRole::CLIENT->value);

        return $user;
    }

    protected function assignAdminRole(User $user): User
    {
        $user->assignRole(UserRole::ADMIN->value);

        return $user;
    }

    protected function assignComplianceOfficerRole(User $user): User
    {
        $user->assignRole(UserRole::COMPLIANCE_OFFICER->value);

        return $user;
    }

    //
}
