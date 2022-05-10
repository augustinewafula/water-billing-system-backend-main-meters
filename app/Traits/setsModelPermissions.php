<?php

namespace App\Traits;

trait setsModelPermissions
{
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
