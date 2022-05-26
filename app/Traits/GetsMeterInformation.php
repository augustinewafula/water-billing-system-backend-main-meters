<?php

namespace App\Traits;

use App\Models\Meter;
use Http;
use JsonException;
use Throwable;

trait GetsMeterInformation
{
    use AuthenticatesMeter;

    /**
     * @throws JsonException
     * @throws Throwable
     */
    public function getShMeterReadings(): ?array
    {
        $database_meter_numbers = $this->getShDatabaseMeters();
        if (empty($database_meter_numbers)) {
            return [];
        }
        $details = $this->getSHMeterDetails($database_meter_numbers, env('SH_METER_USERNAME'), env('SH_METER_PASSWORD'));

        if (env('SH_METER_USERNAME_2') && env('SH_METER_PASSWORD_2')){
            $details_2 = $this->getSHMeterDetails($database_meter_numbers, env('SH_METER_USERNAME_2'), env('SH_METER_PASSWORD_2'));
            $details = array_merge($details, $details_2);
        }
        return $details;
    }

    /**
     * @param array $database_meter_numbers
     * @param $username
     * @param $password
     * @return null
     * @throws JsonException
     */
    private function getSHMeterDetails(array $database_meter_numbers, $username, $password): ?array
    {
        $response = Http::retry(3, 3000)
            ->post('http://47.103.146.199:6071/WebHttpApi_EN/TYGetMeterData.ashx', [
                'MeterIdList' => $database_meter_numbers,
                'UserName' => $username,
                'PassWord' => $password
            ]);
        if ($response->successful()) {
            return json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->MeterDataList;
        }
        return [];
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
