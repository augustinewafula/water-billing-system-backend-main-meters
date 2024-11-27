<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use JsonException;

class ConcentratorService
{
    protected string $baseUrl = 'http://www.server-api.stronpower.com/api/';

    /**
     * @throws JsonException
     */
    public function register(string $concentrator_id, string $name): string
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'Meter', [
                'CompanyName' => env('CONCENTRATOR_COMPANY'),
                'UserName' => env('CONCENTRATOR_USERNAME'),
                'PassWord' => env('CONCENTRATOR_PASSWORD'),
                'ConcentratorID' => $concentrator_id,
                'ConcentratorName' => $name,
            ]);
        Log::info('concentrator register response:' . $response->body());

        return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
    }

    public function registerMeterWithConcentrator(string $meter_number, string $customer_id, string $concentrator_id): mixed
    {
        $response = Http::retry(3, 100)
            ->post($this->baseUrl . 'NewAmiMeter', [
                'MeterID' => $meter_number,
                'MeterID_End' => $meter_number,
                'CompanyName' => env('PREPAID_METER_COMPANY'),
                'UserName' => env('PREPAID_METER_USERNAME'),
                'PassWord' => env('PREPAID_METER_PASSWORD'),
                'MeterType' => 1,
                'CustomerID' => $customer_id,
                'ConcentratorID' => $concentrator_id,
            ]);
        Log::info('prepaid meter with concentrator register response:' . $response->body());

        return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
    }

    public function sendMeterToken(string $meterNumber, string $token): bool
    {
        $response = Http::timeout(80)
            ->retry(3, 150)
            ->post($this->baseUrl . 'VendingMeterSendToken', [
            'CompanyName' => env('CONCENTRATOR_COMPANY'),
            'UserName' => env('CONCENTRATOR_USERNAME'),
            'PassWord' => env('CONCENTRATOR_PASSWORD'),
            'MeterID' => $meterNumber,
            'Token' => $token,
        ]);

        Log::info('concentrator send meter token response:' . $response->body());

        return $response->successful();
    }

}
