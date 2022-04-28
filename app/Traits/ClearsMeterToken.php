<?php

namespace App\Traits;

use Http;
use Log;

trait ClearsMeterToken
{
    protected $baseUrl = 'http://www.shometersapi.stronpower.com/api/';

    public function clearMeterToken($meter_number)
    {
        $response = Http::retry(2, 100)
            ->post($this->baseUrl . 'ClearCredit', [
                'CustomerId' => $meter_number,
                'METER_ID' => $meter_number,
                'COMPANY' => env('PREPAID_METER_COMPANY'),
                'Employee' => '0000',
            ]);
        if ($response->successful()) {
            Log::info('clear credit response:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR);
        }
        return null;
    }

}
