<?php

namespace App\Services;

use Http;
use JsonException;
use Log;

class PrepaidMeterService
{
    protected string $baseUrl = 'http://www.shometersapi.stronpower.com/api/';

    /**
     * @throws JsonException
     */
    public function clearTamperRecord($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post($this->baseUrl . 'ClearTamper', [
                'CustomerId' => $meter_number,
                'METER_ID' => $meter_number,
                'COMPANY' => env('PREPAID_METER_COMPANY'),
                'Employee' => '0000',
            ]);
        if ($response->successful()) {
            Log::info('clear tamper response:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

}
