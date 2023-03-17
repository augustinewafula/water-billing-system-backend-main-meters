<?php

namespace App\Traits;

use App\Enums\MeterCategory;
use App\Enums\PrepaidMeterType;
use Http;
use JsonException;
use Log;

trait ClearsMeterToken
{

    /**
     * @throws JsonException
     */
    public function clearMeterToken(string $meter_number, int $meterCategory, int $prePaidMeterType = PrepaidMeterType::SH): ?string
    {
        if ($meterCategory === MeterCategory::WATER) {
            if ($prePaidMeterType === PrepaidMeterType::SH) {
                return $this->clearWaterToken($meter_number);
            }

            return $this->clearCalinMeterToken($meter_number);
        }

        return $this->clearEnergyToken($meter_number);

    }

    private function clearWaterToken($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post('http://www.shometersapi.stronpower.com/api/ClearCredit', [
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

    /**
     * @throws JsonException
     */
    private function clearCalinMeterToken($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post('http://47.90.189.157:6001/api/Maintenance_ClearCredit', [
                'company_name' => env('CALIN_METER_COMPANY'),
                'user_name' => env('CALIN_METER_USERNAME2'),
                'password' => env('CALIN_METER_PASSWORD'),
                'meter_number' => $meter_number,
            ]);
        if ($response->successful()) {
            Log::info('clear credit response calin meter:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->result;
        }
        return null;
    }

}
