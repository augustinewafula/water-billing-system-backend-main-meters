<?php

namespace Database\Seeders;

use App\Traits\setsModelPermissions;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class ConcentratorPermissionSeeder extends Seeder
{
    use setsModelPermissions;
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $models = ['concentrator'];

        foreach ($models as $model) {
            $model_permissions = $this->setModelPermissions($model);
            foreach ($model_permissions as $model_permission) {
                Permission::create(['name' => $model_permission, 'guard_name' => 'api']);
            }
        }
    }
}
