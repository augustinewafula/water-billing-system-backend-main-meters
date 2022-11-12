<?php

namespace App\Traits;

use Exception;
use Http;
use Log;
use stdClass;
use Throwable;

trait SendsSms
{
    use StoresSms;

    /**
     * @throws Exception
     */
    public function initiateSendSms($to, $message, $user_id, $initiator = 'system', $station_id=null): ?stdClass
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

        try {
            $response = Http::withHeaders([
                'apiKey' => env('AFRICASTKNG_APIKEY'),
                'Accept' => 'application/json'
            ])
                ->asForm()
                ->retry(3, 100)
                ->post($url, $sms_details);
        } catch (Throwable $e) {
            if ($initiator === 'system') {
                $this->storeSms($to, $message, null, 'Failed', 0, $user_id, $station_id);
            }
            throw new Exception('Error sending sms: '.$e->getMessage());
        }

        if ($response->failed()) {
            Log::info('Failed to send sms');
            if ($initiator === 'system') {
                $this->storeSms($to, $message, null, 'Failed', 0, $user_id, $station_id);
            }
        }

        if ($response->successful()) {
            Log::info('response:' . $response->body());
            $response = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->SMSMessageData;
            foreach ($response->Recipients as $recipient) {
                $status = $recipient->status;
                if ($status === 'Success'){
                    $status = 'Sent';
                }
                try {
                    $cost = explode(' ', trim($recipient->cost))[1];
                } catch (Throwable $throwable) {
                    $cost = $recipient->cost;
                }
                $this->storeSms($recipient->number, $message, $recipient->messageId, $status, $cost, $user_id, $station_id);

            }
            return $response;
        }
        return null;

    }

}
