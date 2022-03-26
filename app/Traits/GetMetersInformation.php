<?php

namespace App\Traits;

use App\Models\Meter;
use Http;
use JsonException;
use Throwable;

trait GetMetersInformation
{
    use AuthenticateMeter;

    /**
     * @throws JsonException
     * @throws Throwable
     */
    public function getShMeterReadings()
    {
        $database_meter_numbers = $this->getShDatabaseMeters();
        if (empty($database_meter_numbers)) {
            return null;
        }
        $response = Http::retry(3, 3000)
            ->post('http://47.103.146.199:6071/WebHttpApi_EN/TYGetMeterData.ashx', [
                'MeterIdList' => $database_meter_numbers,
                'UserName' => env('SH_METER_USERNAME'),
                'PassWord' => env('SH_METER_PASSWORD')
            ]);
        if ($response->successful()) {
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->MeterDataList;
        }
        return null;
    }

    public function getShDatabaseMeters(): array
    {
        return Meter::query()
            ->whereHas('type', function ($query) {
                $query->where('name', '=', 'Sh Gprs');
                $query->orWhere('name', '=', 'Sh Nb-iot');
            })
            ->pluck('number')
            ->all();
    }

    /**
     * @throws JsonException
     * @throws Throwable
     */
    public function getChangshaNbIotMeterReadings()
    {
        if ($token = $this->loginChangshaNbIot()) {
            $response = Http::withHeaders([
                'Token' => $token,
            ])
                ->retry(3, 100)
                ->get('http://220.248.173.29:10004/collect/v3/getMeterDataList', [
                    'pageIndex' => 1,
                    'pageSize' => 2000000,
                ]);
            if ($response->successful()) {
                return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->body->recArr;
            }
        }
        return null;

    }
}
