<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use DB;
use Hash;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mail;
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
     * @return Response
     */
    public function getResetToken(Request $request)
    {
        $rules = [
            'email' => 'required|email|exists:users|max:255',
        ];
        $customMessages = [
            'required' => 'The :attribute field is required.',
            'email'=>'Invalid email address',
            'exists'=>'The email does not exist'
        ];
        $this->validate($request, $rules, $customMessages);

        $token = Str::random(64);

        DB::table('password_resets')->insert([
            'email' => $request->email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        try {
            Mail::send('email.forgetPassword', ['token' => $token, 'email' => $request->email], function($message) use($request){
                $message->to($request->email);
                $message->subject('Reset Password');
            });
            $response = ['message'=>'Reset link sent to your email.'];
            return response()->json($response);
        } catch (Throwable $th) {
            Log::error($th);
            $response = ['message' => 'Something went wrong.','errors' =>['email'=>['Unable to send password reset link']]];
            return response()->json($response, 422);
        }

    }

    public function showResetPasswordForm(Request $request, $token) {
        $data = [
            'token'=>$token,
            'email'=>$request->email
        ];
        return view('reset_password')->with($data);
     }

     /**
      * Write code on Method
      *
      * @return response()
      */
     public function submitResetPasswordForm(Request $request)
     {
         $request->validate([
             'email' => 'required|email|exists:users',
             'password' => 'required|string|min:6|confirmed',
             'password_confirmation' => 'required'
         ]);

         $updatePassword = DB::table('password_resets')
                             ->where([
                               'email' => $request->email,
                               'token' => $request->token
                             ])
                             ->first();

         if(!$updatePassword){
             return back()->withInput()->withMessages(['password' => 'Invalid token!']);
         }

         User::where('email', $request->email)
                     ->update(['password' => Hash::make($request->password)]);

         DB::table('password_resets')->where(['email'=> $request->email])->delete();

         return redirect($this->redirectTo);
     }
}
