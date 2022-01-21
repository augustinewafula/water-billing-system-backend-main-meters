<?php

namespace App\Http\Controllers;

use App\Models\User;
use Hash;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function initiateAdminLogin(Request $request){
        return $this->login($request, 'admin');
    }

    public function initiateUserLogin(Request $request){
        return $this->login($request, 'user');
    }

    public function login(Request $request, $user_type){
        $rules = [
            'email' => 'required|email|exists:users',
            'password'  => 'required'
        ];
        $customMessages = [
            'required' => 'The :attribute field is required.',
            'email'=>'Invalid email address',
            'exists'=>'Email does not match any user'
        ];
        $this->validate($request, $rules, $customMessages);
        $user = User::where('email', $request->email)->first();

        if (!Hash::check($request->password, $user->password) || !$user->hasRole($user_type)) {
            $response = ['message' => 'The given data was invalid.','errors' =>['password'=>['Incorrect email or password']]];
            return response()->json($response, 422);
        }

        $token = $user->createToken('Laravel Password Grant Client')->accessToken;
        $response = ['token' => $token];
        return response()->json($response, 200);

    }

    public function logout()
    {
        $token = auth()->guard('api')->user()->token();
        $token->revoke();

        $response = 'You have been successfully logged out!';
        return response()->json($response, 200);
    }

    public function user()
    {
        $user = auth()->guard('api')->user()->only('id', 'name', 'email');
        return response()->json($user);
    }
}
