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
            'user',
            'admin',
            'meter',
            'main-meter',
            'meter-station',
            'meter-token',
            'meter-type',
            'meter-billing',
            'meter-billing-report',
            'meter-charge',
            'meter-reading',
            'mpesa-transaction',
            'monthly-service-charge',
            'monthly-service-charge-payment',
            'monthly-service-charge-report',
            'connection-fee',
            'connection-fee-payment',
            'service-charge',
            'system-user',
            'alert-contact',
            'setting',
            'sms',
            'unresolved-mpesa-transaction',
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
            'list',
            'create',
            'edit',
            'delete',
        ];

        foreach ($permissions as $permission) {
            $permission_name[] = "$model-$permission";
        }
        return $permission_name;
    }
}
