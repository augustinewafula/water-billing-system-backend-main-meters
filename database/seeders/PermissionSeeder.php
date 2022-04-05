<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $models = [
            'users',
            'meters',
            'meter stations',
            'meter tokens',
            'meter types',
            'meter billings',
            'meter billings report',
            'meter charges',
            'meter readings',
            'mpesa transactions',
            'service charges',
            'settings',
            'sms',
            'unresolved mpesa transactions',
        ];

        foreach ($models as $model) {
            $model_permissions = $this->setModelPermissions($model);
            foreach ($model_permissions as $model_permission) {
                Permission::create(['name' => $model_permission, 'guard_name' => 'api']);
            }
        }
    }

    public function setModelPermissions($model): array
    {
        $permission_name = [];
        $permissions = [
            'view',
            'create',
            'edit',
            'delete',
        ];

        foreach ($permissions as $permission) {
            $permission_name[] = "$permission $model";
        }
        return $permission_name;
    }
}
