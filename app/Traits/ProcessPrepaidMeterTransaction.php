<?php

namespace App\Traits;

use Http;
use JsonException;
use Log;

trait ProcessPrepaidMeterTransaction
{
    protected $baseUrl = 'http://www.shometersapi.stronpower.com/api/';

    /**
     * @throws JsonException
     */
    public function registerPrepaidMeter($meter_id): void
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'Meter', [
                'METER_ID' => $meter_id,
                'COMPANY' => env('PREPAID_METER_COMPANY'),
                'METER_TYPE' => 1,
                'ApiToken' => $this->login(),
            ]);
        Log::info('register response:' . $response->body());
    }

    /**
     * @throws JsonException
     */
    public function login(): ?string
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'login', [
                'Companyname' => env('PREPAID_METER_COMPANY'),
                'Username' => env('PREPAID_METER_USERNAME'),
                'Password' => env('PREPAID_METER_PASSWORD'),
            ]);
        if ($response->successful()) {
            Log::info('response:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

    /**
     * @throws JsonException
     */
    public function generateMeterToken($meter_id, $amount): ?string
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'vending', [
                'CustomerId' => $meter_id,
                'MeterId' => $meter_id,
                'Price' => 200,
                'Rate' => 1,
                'Amount' => $amount,
                'AmountTmp' => 'KES',
                'Company' => env('PREPAID_METER_COMPANY'),
                'Employee' => '0000',
                'ApiToken' => $this->login(),
            ]);
        if ($response->successful()) {
            Log::info('vending response:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }
}
