<?php

namespace Database\Seeders;

use App\Traits\setsModelPermissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    use setsModelPermissions;
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $permissions = Permission::pluck('name')
            ->all();

        $admin_blacklist_permissions = array_merge($this->constructModelPermission(['service-charge', 'role']));
        $supervisor_blacklist_permissions = array_merge($this->constructModelPermission(['admin']), [
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
            'monthly-service-charge-delete']);

        $admin_permissions = $this->except($permissions, $admin_blacklist_permissions);
        $supervisor_permissions = $this->except($admin_permissions, $supervisor_blacklist_permissions);
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

    public function constructModelPermission($models): array
    {
        $model_permissions = [];
        foreach ($models as $model){
            $model_permission = $this->setModelPermissions($model);
            $model_permissions[] = $model_permission;
        }
        return array_merge(...$model_permissions);
    }

    public function except($array, $filter): array
    {
        return array_diff($array, $filter);
    }
}
