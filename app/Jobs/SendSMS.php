<?php

namespace App\Jobs;

use Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSMS implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $to, $message;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($to, $message)
    {
        $this->to = $to;
        $this->message = $message;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        env('AFRICASTKNG_USERNAME') === 'sandbox' ?
            $url = 'https://api.sandbox.africastalking.com/version1/messaging' :
            $url = 'https://api.africastalking.com/version1/messaging';
        $sms_details = [
            'username' => env('AFRICASTKNG_USERNAME'),
            'to' => $this->to,
            'message' => $this->message,
        ];
        if (!empty(env('AFRICASTKNG_SENDER_ID'))) {
            $sms_details = array_merge($sms_details, ['from' => env('AFRICASTKNG_SENDER_ID')]);
        }
        Http::withHeaders([
            'apiKey' => env('AFRICASTKNG_APIKEY'),
            'Accept' => 'application/json'
        ])
            ->asForm()
            ->retry(3, 100)
            ->post($url,);
    }

}
