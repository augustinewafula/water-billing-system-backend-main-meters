<?php

namespace App\Traits;

use App\Jobs\SendSetPasswordEmail;
use Carbon\Carbon;
use DB;
use Str;

trait SendsSetPasswordEmail
{

    /**
     * @param $email
     * @return void
     */
    public function sendSetPasswordEmail($email): void
    {
        $token = Str::random(64);
        DB::table('password_resets')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        $url = env('APP_FRONTEND_URL') . "reset-password/$token?email=$email&action=set";
        SendSetPasswordEmail::dispatch($email, $url);
    }

}
