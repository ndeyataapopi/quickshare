<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ImpersonatePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate([
            'name' => 'impersonate_users',
            'guard_name' => 'web',
        ]);

        User::whereHas('roles', fn($q) => $q->where('name', 'admin'))
            ->get()
            ->each(fn(User $user) => $user->givePermissionTo($permission));
    }
}
