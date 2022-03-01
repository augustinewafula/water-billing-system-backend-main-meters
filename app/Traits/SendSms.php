<?php

namespace App\Traits;

use Exception;
use Http;
use Log;
use stdClass;
use Throwable;

trait SendSms
{
    use StoreSms;

    /**
     * @throws Exception
     */
    public function initiateSendSms($to, $message): ?stdClass
    {
        env('AFRICASTKNG_USERNAME') === 'sandbox' ?
            $url = 'https://api.sandbox.africastalking.com/version1/messaging' :
            $url = 'https://api.africastalking.com/version1/messaging';
        $sms_details = [
            'username' => env('AFRICASTKNG_USERNAME'),
            'to' => $to,
            'message' => $message,
        ];
        if (!empty(env('AFRICASTKNG_SENDER_ID'))) {
            $sms_details = array_merge($sms_details, ['from' => env('AFRICASTKNG_SENDER_ID')]);
        }
        $response = Http::withHeaders([
            'apiKey' => env('AFRICASTKNG_APIKEY'),
            'Accept' => 'application/json'
        ])
            ->asForm()
            ->retry(3, 100)
            ->post($url, $sms_details);

        if ($response->successful()) {
            Log::info('response:' . $response->body());
            $response = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->SMSMessageData;
            foreach ($response->Recipients as $recipient) {
                try {
                    $cost = explode(' ', trim($recipient->cost))[1];
                } catch (Throwable $throwable) {
                    $cost = $recipient->cost;
                }
                $this->storeSms($recipient->number, $message, $recipient->status, $cost);
            }
            return $response;
        }
        return null;

    }

}
