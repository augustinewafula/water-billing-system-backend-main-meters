<?php

namespace App\Traits;

use App\Enums\MeterCategory;
use Http;
use JsonException;
use Log;

trait ClearsMeterToken
{
    protected $baseUrl = 'http://www.shometersapi.stronpower.com/api/';

    /**
     * @throws JsonException
     */
    public function clearMeterToken(string $meter_number, int $meterCategory): ?string
    {
        if ($meterCategory === MeterCategory::WATER) {
            return $this->clearWaterToken($meter_number);
        }

        return $this->clearEnergyToken($meter_number);

    }

    private function clearWaterToken($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post($this->baseUrl . 'ClearCredit', [
                'CustomerId' => $meter_number,
                'METER_ID' => $meter_number,
                'COMPANY' => env('PREPAID_METER_COMPANY'),
                'Employee' => '0000',
            ]);
        if ($response->successful()) {
            Log::info('clear credit response water meter:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

    /**
     * @throws JsonException
     */
    private function clearEnergyToken($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post('http://www.server-api.stronpower.com/api/ClearCredit', [
                'CompanyName' => env('PREPAID_ENERGY_METER_COMPANY'),
                'UserName' => env('PREPAID_ENERGY_METER_USERNAME'),
                'PassWord' => env('PREPAID_ENERGY_METER_PASSWORD'),
                'Meter_ID' => $meter_number,
                'CustomerId' => $meter_number,
            ]);
        if ($response->successful()) {
            Log::info('clear credit response energy meter:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

}
