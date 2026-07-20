<?php

namespace Database\Seeders;

use App\Enums\UserRole;
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
            // KYC
            'approve_kyc',
            'reject_kyc',

            // Users
            'manage_users',

            // Loans
            'manage_loans',
            'approve_loans',
            'reject_loans',

            // Funding / Disbursements
            'manage_funding',
            'manage_disbursements',

            // Repayments / Collections
            'manage_repayments',
            'manage_collections',

            // Reports / Audit
            'view_reports',
            'view_audit_logs',

            // Fraud / Referrals
            'manage_fraud_alerts',
            'manage_referrals',

            // Impersonation
            'impersonate_users',

            // Client-only
            'view_own_loans',
            'request_loan',
            'make_repayment',
            'fund_loan',
            'view_marketplace',
            'view_own_portfolio',
            'view_lender_earnings',
            'submit_kyc',
            'view_own_profile',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        $admin = Role::firstOrCreate(['name' => UserRole::ADMIN->value, 'guard_name' => 'web']);
        $admin->syncPermissions([
            'approve_kyc',
            'reject_kyc',
            'manage_users',
            'manage_loans',
            'approve_loans',
            'reject_loans',
            'manage_funding',
            'manage_disbursements',
            'manage_repayments',
            'manage_collections',
            'view_reports',
            'view_audit_logs',
            'manage_fraud_alerts',
            'manage_referrals',
            'view_marketplace',
        ]);

        $complianceOfficer = Role::firstOrCreate(['name' => UserRole::COMPLIANCE_OFFICER->value, 'guard_name' => 'web']);
        $complianceOfficer->syncPermissions([
            'approve_kyc',
            'reject_kyc',
            'view_reports',
            'manage_users',
        ]);

        $financeOfficer = Role::firstOrCreate(['name' => UserRole::FINANCE_OFFICER->value, 'guard_name' => 'web']);
        $financeOfficer->syncPermissions([
            'manage_funding',
            'manage_disbursements',
            'manage_repayments',
            'manage_collections',
            'view_reports',
        ]);

        // Client role - both borrower and lender
        $client = Role::firstOrCreate(['name' => UserRole::CLIENT->value, 'guard_name' => 'web']);
        $client->syncPermissions([
            'request_loan',
            'view_own_loans',
            'make_repayment',
            'fund_loan',
            'view_marketplace',
            'view_own_portfolio',
            'view_lender_earnings',
            'view_own_profile',
            'submit_kyc',
            'manage_referrals',
            'view_reports',
        ]);
    }
}
