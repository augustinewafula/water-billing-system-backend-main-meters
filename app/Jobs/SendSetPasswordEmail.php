<?php

namespace App\Jobs;

use Carbon\Carbon;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mail;
use Str;

class SendSetPasswordEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($email)
    {
        $this->email = $email;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $token = Str::random(64);
        DB::table('password_resets')->insert([
            'email' => $this->email,
            'token' => $token,
            'created_at' => Carbon::now()
        ]);

        Mail::send('emails.setPassword', ['token' => $token, 'email' => $this->email], function ($message) {
            $message->to($this->email);
            $message->subject('Set Password');

        });
    }
}
