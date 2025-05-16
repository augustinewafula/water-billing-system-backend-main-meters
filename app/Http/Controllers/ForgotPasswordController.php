<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use DB;
use Hash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mail;
use Illuminate\Validation\Rules\Password;
use Throwable;

class ForgotPasswordController extends Controller
{
    protected $redirectTo = '/success';
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset emails and
    | includes a trait which assists in sending these notifications from
    | your application to your users. Feel free to explore this trait.
    |
     */
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Send a reset link to the given user without revealing if the email exists.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function getResetToken(Request $request): JsonResponse
    {
        $rules = [
            'email' => 'required|email|max:255',
        ];

        $customMessages = [
            'required' => 'The :attribute field is required.',
            'email' => 'Invalid email address',
        ];

        $this->validate($request, $rules, $customMessages);

        $email = $request->input('email');
        $user = User::where('email', $email)->first();

        if (!$user) {
            // Log attempt for auditing/security purposes
            Log::warning('Password reset requested for non-existent email.', [
                'email' => $request->email,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
            // Always return the same response
            return response()->json([
                'message' => 'If we have an account associated with this email, you’ll receive a password reset link shortly.'
            ]);
        }

        // Delete any existing reset tokens for the same email
        DB::table('password_resets')->where('email', $email)->delete();

        $token = Str::random(64);

        DB::table('password_resets')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        try {
            Mail::queue(new \App\Mail\PasswordResetMail($token, $email));
        } catch (Throwable $th) {
            Log::error($th);
            // Still return a generic response for security
            return response()->json([
                'message' => 'If we have an account associated with this email, you’ll receive a password reset link shortly.'
            ]);
        }

        return response()->json([
            'message' => 'If we have an account associated with this email, you’ll receive a password reset link shortly.'
        ]);
    }

    public function showResetPasswordForm(Request $request, $token)
    {
        $data = [
            'token' => $token,
            'email' => $request->email,
            'action' => ucfirst($request->action)
        ];
        return view('reset_password')->with($data);
    }


    public function submitResetPasswordForm(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users',
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->uncompromised()],
            'password_confirmation' => 'required'
        ]);

        $updatePassword = DB::table('password_resets')
            ->where([
                'email' => $request->email,
                'token' => $request->token])
            ->first();

         if(!$updatePassword) {
             if ($request->isJson()) {
                 $response = ['message' => 'The given data was invalid.', 'errors' => ['password' => ['Invalid token']]];
                 return response()->json($response, 422);
             }
             return back()->withInput()->withMessages(['password' => 'Invalid token!']);
         }

        User::where('email', $request->email)
            ->update(['password' => Hash::make($request->password), 'should_reset_password' => false]);

        DB::table('password_resets')->where(['email' => $request->email])->delete();

        if ($request->isJson()) {
            return response()->json(['message' => 'You can now login with your new password']);
        }

        return redirect($this->redirectTo);
    }
}
