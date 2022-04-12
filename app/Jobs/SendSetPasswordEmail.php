<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mail;

class SendSetPasswordEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email, $action_url;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($email, $action_url)
    {
        $this->email = $email;
        $this->action_url = $action_url;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {

        Mail::send('emails.setPassword', ['email' => $this->email, 'action_url' => $this->action_url], function ($message) {
            $message->to($this->email);
            $message->subject('Set Password');

        });
    }
}
