<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\validCurrentPassword;
use Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * @throws ValidationException
     */
    public function initiateAdminLogin(Request $request): JsonResponse
    {
        return $this->login($request, 'admin');
    }

    /**
     * @throws ValidationException
     */
    public function initiateUserLogin(Request $request): JsonResponse
    {
        return $this->login($request, 'user');
    }

    /**
     * @throws ValidationException
     */
    public function login(Request $request, $user_type): JsonResponse
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
        $user = User::where('email', $request->email)->first();

        if (!Hash::check($request->password, $user->password) || !$user->hasRole($user_type)) {
            $response = ['message' => 'The given data was invalid.', 'errors' => ['password' => ['Incorrect email or password']]];
            return response()->json($response, 422);
        }

        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        $response = ['token' => $token, 'name' => $user->name, 'email' => $user->email];

        if ($user_type === 'admin') {
            $role = $user->hasRole('super-admin') ? 'super-admin' : 'admin';
            $response['role'] = $role;
        }
        return response()->json($response, 200);

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
        $token = auth()->guard('api')->user()->token();
        $token->revoke();

        $response = 'You have been successfully logged out!';
        return response()->json($response, 200);
    }

    public function user(): JsonResponse
    {
        $user = auth()->guard('api')->user()->only('id', 'name', 'email', 'phone');
        return response()->json($user);
    }
}
