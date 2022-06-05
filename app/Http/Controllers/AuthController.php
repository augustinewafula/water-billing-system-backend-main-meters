<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\validCurrentPassword;
use Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

class AuthController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function initiateAdminLogin(Request $request): JsonResponse
    {
        return $this->login($request);
    }

    /**
     * @throws ValidationException
     */
    public function initiateUserLogin(Request $request): JsonResponse
    {
        return $this->login($request);
    }

    /**
     * @throws ValidationException
     */
    public function login(Request $request): JsonResponse
    {
        $rules = [
            'email' => 'required|email|exists:users',
            'password' => 'required'
        ];
        $customMessages = [
            'required' => 'The :attribute field is required.',
            'email' => 'Invalid email address',
            'exists' => 'Invalid email or password'
        ];
        $this->validate($request, $rules, $customMessages);
        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->should_reset_password){
            $response = ['message' => 'Your password appears to have been compromised; please use Forget Password to reset your account password.'];
            return response()->json($response, 422);
        }

        if (!Hash::check($request->password, $user->password)) {
            $response = ['message' => 'The given data was invalid.', 'errors' => ['password' => ['Incorrect email or password']]];
            return response()->json($response, 422);
        }

        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        $permissions = $user->getAllPermissions()->pluck('name');
        if ($user->hasRole('super-admin')){
            $permissions = Permission::pluck('name')
                ->all();
        }
        $response = [
            'token' => $token,
            'name' => $user->name,
            'email' => $user->email,
            'permissions' => $permissions
        ];
        activity()
            ->causedBy($user)
            ->performedOn($user)
            ->log('logged in');
        return response()->json($response);

    }

    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string'],
            'email' => ['required', 'email', 'max:50'],
            'phone' => ['nullable', 'numeric'],
        ]);
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);
        return response()->json('updated');
    }

    public function updatePassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', new validCurrentPassword()],
            'new_password' => 'required|min:8|confirmed'
        ]);
        $user->update([
            'password' => bcrypt($request->new_password),
        ]);
        return response()->json('updated');
    }

    public function logout(): JsonResponse
    {
        $user = auth()->guard('api')->user();
        if ($user === null){
            return response()->json('Not logged in', 422);
        }
        activity()
            ->performedOn($user)
            ->log('logged out');
        $token = $user->token();
        $token->revoke();

        $response = 'You have been successfully logged out!';
        return response()->json($response, );
    }

    public function user(): JsonResponse
    {
        $user = auth()->guard('api')->user()->only('id', 'name', 'email', 'phone');
        return response()->json($user);
    }
}
