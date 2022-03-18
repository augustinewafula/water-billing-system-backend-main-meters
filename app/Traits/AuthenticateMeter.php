<?php

namespace App\Traits;

use Http;
use JsonException;
use Log;

trait AuthenticateMeter
{
    /**
     * @throws JsonException|JsonException
     */
    public function loginChangshaNbIot(): ?string
    {
        $response = Http::retry(3, 3000)
            ->get('http://220.248.173.29:10004/collect/v3/getToken', [
                'userName' => env('CHANGSHA_NBIOT_METER_USERNAME'),
                'passWord' => env('CHANGSHA_NBIOT_METER_PASSWORD'),
            ]);
        if ($response->successful()) {
            Log::info('response:' . $response->body());
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->body->token;
        }
        return null;
    }
}
