<?php

namespace App\Jobs;

use App\Traits\SendsSms;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SendsSms;

    protected $to, $message, $user_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($to, $message, $user_id)
    {
        $this->to = $to;
        $this->message = $message;
        $this->user_id = $user_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        $this->initiateSendSms($this->to, $this->message, $this->user_id);
    }

}
