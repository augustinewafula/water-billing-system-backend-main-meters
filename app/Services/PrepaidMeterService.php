<?php

namespace App\Services;

use App\Enums\MeterCategory;
use Http;
use JsonException;
use Log;

class PrepaidMeterService
{
    protected string $baseUrl = 'http://www.shometersapi.stronpower.com/api/';

    /**
     * @throws JsonException
     */
    public function clearTamperRecord(string $meter_number, int $meterCategory): ?string
    {
        if ($meterCategory === MeterCategory::WATER) {
            return $this->clearWaterTamper($meter_number);
        }

        return $this->clearEnergyTamper($meter_number);

    }

    private function clearWaterTamper($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post($this->baseUrl . 'ClearTamper', [
                'CustomerId' => $meter_number,
                'METER_ID' => $meter_number,
                'COMPANY' => env('PREPAID_METER_COMPANY'),
                'Employee' => '0000',
            ]);
        if ($response->successful()) {
            Log::info('clear tamper response water meter:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

    /**
     * @throws JsonException
     */
    private function clearEnergyTamper($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post('http://www.server-api.stronpower.com/api/ClearTamper', [
                'CompanyName' => env('PREPAID_ENERGY_METER_COMPANY'),
                'UserName' => env('PREPAID_ENERGY_METER_USERNAME'),
                'PassWord' => env('PREPAID_ENERGY_METER_PASSWORD'),
                'Meter_ID' => $meter_number,
                'CustomerId' => $meter_number,
            ]);
        if ($response->successful()) {
            Log::info('clear tamper response energy meter:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

}
