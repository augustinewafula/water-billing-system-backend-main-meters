<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $permissions = Permission::pluck('name')
            ->all();

        $admin_permissions = $this->except($permissions, [
            'service-charge-list',
            'service-charge-create',
            'service-charge-edit',
            'service-charge-delete',
        ]);
        $supervisor_permissions = $this->except($admin_permissions, [
            'admin-list',
            'admin-create',
            'admin-edit',
            'admin-delete',
            'meter-edit',
            'meter-delete',
            'meter-station-edit',
            'meter-station-delete',
            'meter-type-delete',
            'meter-token-delete',
            'user-edit',
            'user-delete',
            'meter-edit',
            'meter-type-edit',
            'meter-token-edit',
            'monthly-service-charge-create',
            'monthly-service-charge-edit',
            'monthly-service-charge-delete',
        ]);
        $user_permissions = [
            'meter-list',
            'user-list',
            'user-edit',
            'meter-billing-list',
            'meter-reading-list',
        ];

        $user = Role::create([
            'name' => 'user',
            'guard_name' => 'api',
        ]);
        $user->givePermissionTo($user_permissions);

        $supervisor = Role::create([
            'name' => 'supervisor',
            'guard_name' => 'api',
        ]);
        $supervisor->givePermissionTo($supervisor_permissions);

        $admin = Role::create([
            'name' => 'admin',
            'guard_name' => 'api',
        ]);
        $admin->givePermissionTo($admin_permissions);

        Role::create([
            'name' => 'super-admin',
            'guard_name' => 'api',
        ]);
        // gets all permissions via Gate::before rule; see AuthServiceProvider
    }

    public function except($array, $filter): array
    {
        return array_diff($array, $filter);
    }
}
