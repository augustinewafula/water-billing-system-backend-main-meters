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
            'view service charges',
            'create service charges',
            'edit service charges',
            'delete service charges',
        ]);
        $supervisor_permissions = $this->except($admin_permissions, [
            'delete users',
            'delete meters',
            'delete meter types',
            'delete meter tokens',
            'edit users',
            'edit meters',
            'edit meter types',
            'edit meter tokens',
        ]);
        $user_permissions = [
            'view meters',
            'view users',
            'edit users',
            'view meter billings',
            'view meter readings',
            'delete service charges',
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
