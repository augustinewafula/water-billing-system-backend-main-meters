<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\User;
use App\Traits\setsModelPermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use JsonException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    use setsModelPermissions;
    /**
     * Display a listing of the resource.
     *
     */
    public function index(): JsonResponse
    {
        return response()
            ->json(
                Role::where('name', '!=', 'super-admin')
                    ->where('name', '!=', 'user')
                    ->pluck('name')
                    ->all()
            );
    }


    public function show($role): JsonResponse
    {
        $permissions = Role::findByName($role)->permissions->pluck('name')->all();
        $model_and_actions = [];
        foreach ($permissions as $permission){
            $model_name = substr($permission, 0, strrpos($permission, '-'));
            $formatted_model_name = str_replace('-', ' ', $model_name);
            $formatted_action_name = substr($permission, strrpos($permission, '-') + 1);
            $model_and_actions[] = ['name' => $formatted_model_name, 'action' => $formatted_action_name];
        }
        $model_and_actions = $this->_group_by($model_and_actions, 'name');
        $formatted_model_and_actions = [];
        foreach ($model_and_actions as $key => $model_action){
            $formatted_model_and_actions[] = ['name' => $key, 'actions' => $model_action];
        }
        return response()->json($formatted_model_and_actions);
    }

    /**
     * @throws JsonException
     */
    public function store(CreateRoleRequest $request): JsonResponse
    {
        $permission_names = $this->createOrUpdatePermissions($request);

        $role = Role::create([
            'name' => $request->name,
            'guard_name' => 'api',
        ]);
        $role->givePermissionTo($permission_names);
        return response()->json('created', 201);

    }

    /**
     * @throws JsonException
     */
    public function update(UpdateRoleRequest $request, $role_name): JsonResponse
    {
        $permission_names = $this->createOrUpdatePermissions($request);

        $role = Role::findByName($role_name);
        $role->syncPermissions($permission_names);

        $users = User::with('tokens')
            ->role($role_name)
            ->get();
        foreach ($users as $user){
            foreach($user->tokens as $token) {
                $token->revoke();
            }
        }
        return response()->json('updated');

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param $name
     * @return JsonResponse
     */
    public function destroy($name): JsonResponse
    {
        $role = Role::findByName($name);
        $role->delete();
        return response()->json('updated');
    }

    public function _group_by($array, $key): array
    {
        $return = [];
        foreach($array as $val) {
            $return[$val[$key]][] = $val['action'];
        }
        return $return;
    }

    public function getIgnoredModels(): array
    {
        return ['connection fee payment', 'meter billing', 'meter billing report', 'meter type', 'monthly service charge', 'monthly service charge payment', 'monthly service charge report', 'unresolved mpesa transaction', 'mpesa transaction', 'setting', 'meter charge', 'sms'];
    }

    public function permissionModelsIndex(): JsonResponse
    {
        $permissions = Permission::pluck('name')
            ->all();
        $permission_models = [];
        foreach ($permissions as $permission){
            $model_name = substr($permission, 0, strrpos($permission, '-'));
            $formatted_model_name = str_replace('-', ' ', $model_name);
            $permission_models[] = $formatted_model_name;
        }
        $unique_permission_models = array_unique($permission_models);
        $unique_permission_models = $this->filterSpecificNames($unique_permission_models, $this->getIgnoredModels());
        return response()->json($unique_permission_models);

    }

    /**
     * @param array $unique_permission_models
     * @param array $names
     * @return array
     */
    private function filterSpecificNames(array $unique_permission_models, array $names): array
    {
        foreach ($names as $name){
            if (($key = array_search($name, $unique_permission_models, true)) !== false) {
                unset($unique_permission_models[$key]);
            }
        }
        return Arr::flatten($unique_permission_models);
    }

    /**
     * @param $request
     * @return array
     * @throws JsonException
     */
    private function createOrUpdatePermissions($request): array
    {
        $permissions = json_decode($request->permissions, false, 512, JSON_THROW_ON_ERROR);
        $permission_names = [];
        foreach ($permissions as $permission) {
            if (isset($permission->create) && $permission->create) {
                $permission_names[] = str_replace(' ', '-', "$permission->name create");
            }
            if (isset($permission->list) && $permission->list) {
                $permission_names[] = str_replace(' ', '-', "$permission->name list");
            }
            if (isset($permission->edit) && $permission->edit) {
                $permission_names[] = str_replace(' ', '-', "$permission->name edit");
            }
            if (isset($permission->delete) && $permission->delete) {
                $permission_names[] = str_replace(' ', '-', "$permission->name delete");
            }
        }

        foreach ($this->getIgnoredModels() as $model) {
            $permission_names[] = $this->setModelPermissions(str_replace(' ', '-', $model));
        }

        foreach ($permission_names as $permission_name) {
            Permission::updateOrCreate(
                ['name' => $permission_name],
                ['guard' => 'api']
            );
        }
        return $permission_names;
    }
}
