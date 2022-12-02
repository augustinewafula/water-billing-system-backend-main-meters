<?php

namespace App\Jobs;

use App\Traits\NotifiesOnJobFailure;
use App\Traits\SendsSms;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SendsSms, NotifiesOnJobFailure;

    protected $to, $message, $user_id, $station_id;

    public int $tries = 2;
    public bool $failOnTimeout = true;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($to, $message, $user_id, $station_id=null)
    {
        $this->to = $to;
        $this->message = $message;
        $this->user_id = $user_id;
        $this->station_id = $station_id;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Exception
     */
    public function handle(): void
    {
        $this->initiateSendSms($this->to, $this->message, $this->user_id, 'system', $this->station_id);
    }

}
