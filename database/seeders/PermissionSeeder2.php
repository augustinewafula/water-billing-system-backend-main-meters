<?php

namespace Database\Seeders;

use App\Traits\setsModelPermissions;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder2 extends Seeder
{
    use setsModelPermissions;
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $models = ['statistics'];

        foreach ($models as $model) {
            $model_permissions = $this->setModelPermissions($model);
            foreach ($model_permissions as $model_permission) {
                Permission::create(['name' => $model_permission, 'guard_name' => 'api']);
            }
        }
    }
}
