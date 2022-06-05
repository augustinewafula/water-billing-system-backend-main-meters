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
     * Send a reset link to the given user.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    public function getResetToken(Request $request): JsonResponse
    {
        $rules = [
            'email' => 'required|email|exists:users|max:255',
        ];
        $customMessages = [
            'required' => 'The :attribute field is required.',
            'email' => 'Invalid email address',
            'exists' => 'There is no user registered with this email'
        ];
        $this->validate($request, $rules, $customMessages);

        $token = Str::random(64);

        DB::table('password_resets')->insert([
            'email' => $request->email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        try {
            Mail::send('emails.forgetPassword', ['token' => $token, 'email' => $request->email], static function ($message) use ($request) {
                $message->to($request->email);
                $message->subject('Reset Password');
            });
            $response = ['message' => 'Instructions on how to reset your password have been sent to your email.'];
            return response()->json($response);
        } catch (Throwable $th) {
            Log::error($th);
            $response = ['message' => 'Something went wrong.','errors' =>['email'=>['Unable to send password reset link']]];
            return response()->json($response, 422);
        }

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
            'password' => ['required', 'string', 'confirmed', Password::min(8)->mixedCase()->uncompromised()],
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
