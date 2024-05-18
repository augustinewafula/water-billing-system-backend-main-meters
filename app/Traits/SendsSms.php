<?php

namespace App\Traits;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;
use Throwable;

trait SendsSms
{
    use StoresSms;

    /**
     * Initiates the sending of an SMS.
     *
     * @throws Exception
     */
    public function initiateSendSms(
        string $to,
        string $message,
        string $user_id,
        string $initiator = 'system',
        ?string $station_id = null,
        bool $isMasked = false
    ): ?stdClass {
        if ($this->isHashed($to)) {
            return $this->sendHashedSms($message, $to, env('AFRICASTKNG_SENDER_ID'), $to, $user_id, $initiator, $station_id);
        }

        $url = $this->getAfricasTalkingUrl();
        $smsDetails = $this->getSmsDetails($to, $message);

        try {
            $response = Http::withHeaders($this->getHttpHeaders())
                ->asForm()
                ->retry(3, 100)
                ->post($url, $smsDetails);

            return $this->handleResponse($response, $to, $message, $initiator, $user_id, $station_id);
        } catch (Throwable $e) {
            $this->handleSendFailure($to, $message, $initiator, $user_id, $station_id);
            throw new Exception('Error sending SMS: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Sends an SMS to hashed numbers.
     *
     * @throws Exception
     */
    public function sendHashedSms(
        string $message,
        string $maskedNumber,
        string $senderId,
        string $phoneNumbers, // Ensure this is an array of phone numbers
        string $user_id,
        string $initiator = 'system',
        ?string $station_id = null,
        string $telco = 'Safaricom'
    ): ?stdClass {
        $url = $this->getAfricasTalkingBulkUrl();

        $smsDetails = [
            'username' => env('AFRICASTKNG_USERNAME'),
            'message' => $message,
            'maskedNumber' => $maskedNumber,
            'telco' => $telco,
            'senderId' => $senderId,
            'phoneNumbers' => [],
        ];

        try {
            $response = Http::withHeaders($this->getHttpHeaders())
                ->accept('application/json')
                ->retry(3, 100)
                ->post($url, $smsDetails);

            if ($response->successful()) {
                return $this->handleResponse($response, $phoneNumbers, $message, $initiator, $user_id, $station_id);
            } else {
                Log::error('Failed to send hashed SMS', [
                    'response' => $response->body(),
                    'status' => $response->status(),
                ]);
                $this->handleSendFailure($phoneNumbers, $message, $initiator, $user_id, $station_id);
                return null;
            }
        } catch (Throwable $e) {
            Log::error('Error sending hashed SMS', [
                'exception' => $e->getMessage(),
            ]);
            $this->handleSendFailure($phoneNumbers, $message, $initiator, $user_id, $station_id);
            throw new Exception('Error sending hashed SMS: ' . $e->getMessage(), 0, $e);
        }
    }

    private function getAfricasTalkingUrl(): string
    {
        return env('AFRICASTKNG_USERNAME') === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging'
            : 'https://api.africastalking.com/version1/messaging';
    }

    private function getAfricasTalkingBulkUrl(): string
    {
        return env('AFRICASTKNG_USERNAME') === 'sandbox'
            ? 'https://api.sandbox.africastalking.com/version1/messaging/bulk'
            : 'https://api.africastalking.com/version1/messaging/bulk';
    }

    private function getSmsDetails(string $to, string $message): array
    {
        $details = [
            'username' => env('AFRICASTKNG_USERNAME'),
            'to' => $to,
            'message' => $message,
        ];

        if (!empty(env('AFRICASTKNG_SENDER_ID'))) {
            $details['from'] = env('AFRICASTKNG_SENDER_ID');
        }

        return $details;
    }

    private function getHttpHeaders(): array
    {
        return [
            'apiKey' => env('AFRICASTKNG_APIKEY'),
            'Accept' => 'application/json',
        ];
    }

    private function handleResponse($response, $recipients, $message, $initiator, $user_id, $station_id): ?stdClass
    {
        if ($response->failed()) {
            Log::info('Failed to send SMS');
            $this->handleSendFailure($recipients, $message, $initiator, $user_id, $station_id);
        } elseif ($response->successful()) {
            Log::info('Response: ' . $response->body());
            $this->handleSuccessfulResponse($response, $message, $user_id, $station_id);
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->SMSMessageData;
        }

        return null;
    }

    private function handleSendFailure($recipients, $message, $initiator, $user_id, $station_id): void
    {
        if ($initiator === 'system') {
            $this->storeSms($recipients, $message, null, 'Failed', 0, $user_id, $station_id);
        }
    }

    /**
     * @throws \JsonException
     */
    private function handleSuccessfulResponse($response, $message, $user_id, $station_id): void
    {
        $response = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->SMSMessageData;
        foreach ($response->Recipients as $recipient) {
            $status = $recipient->status === 'Success' ? 'Sent' : $recipient->status;
            try {
                $cost = explode(' ', trim($recipient->cost))[1];
            } catch (Throwable $throwable) {
                $cost = $recipient->cost;
            }
            $this->storeSms($recipient->number, $message, $recipient->messageId, $status, $cost, $user_id, $station_id);
        }
    }

    private function isHashed(string $number): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $number) === 1;
    }
}
