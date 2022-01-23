<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Traits\GeneratePassword;
use Exception;
use Hash;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    use GeneratePassword;

    /**
     * Display a listing of the resource.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $users = User::whereHas("roles",
            static function ($q) {
                $q->where("name", "user");
            })->get(['id', 'name', 'email', 'meter_id', 'first_bill']);
        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateUserRequest $request
     * @return JsonResponse
     * @throws Exception
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $password = $this->generatePassword(10);
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->meter_id = $request->meter_id;
        $user->password = Hash::make($password);
        $user->save();

        return response()->json($user, 201);
    }

    /**
     * Display the specified resource.
     *
     * @param User $user
     * @return JsonResponse
     */
    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateUserRequest $request
     * @param User $user
     * @return JsonResponse
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $user->update($request->validated());
        return response()->json($user);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param User $user
     * @return JsonResponse
     */
    public function destroy(User $user)
    {
        $user->delete();
        return response()->json('deleted');
    }
}
