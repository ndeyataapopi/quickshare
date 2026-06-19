<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed roles, permissions, and their assignments.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'approve_kyc',
            'reject_kyc',
            'manage_loans',
            'manage_users',
            'manage_repayments',
            'view_reports',
            'view_own_loans',
            'request_loan',
            'make_repayment',
            'fund_loan',
            'view_marketplace',
            'view_own_portfolio',
            'submit_kyc',
            'view_own_profile',
            'manage_referrals',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        $admin = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            'approve_kyc',
            'reject_kyc',
            'manage_loans',
            'manage_users',
            'manage_repayments',
            'view_reports',
            'view_marketplace',
            'manage_referrals',
        ]);

        $complianceOfficer = Role::firstOrCreate(['name' => 'compliance_officer', 'guard_name' => 'web']);
        $complianceOfficer->syncPermissions([
            'approve_kyc',
            'reject_kyc',
            'view_reports',
            'manage_users',
        ]);

        // Client role - both borrower and lender
        $client = Role::firstOrCreate(['name' => 'client', 'guard_name' => 'web']);
        $client->syncPermissions([
            'request_loan',
            'view_own_loans',
            'make_repayment',
            'fund_loan',
            'view_marketplace',
            'view_own_portfolio',
            'view_own_profile',
            'submit_kyc',
            'manage_referrals',
            'view_reports',
        ]);
    }
}
