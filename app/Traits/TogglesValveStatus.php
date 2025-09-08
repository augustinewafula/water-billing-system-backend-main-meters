<?php

namespace App\Traits;

use App\Enums\PrepaidMeterType;
use App\Enums\ValveStatus;
use App\Models\MeterType;
use App\Services\HexingMeterService;
use Http;
use JsonException;
use Log;
use Throwable;

trait TogglesValveStatus
{
    use AuthenticatesMeter;

    /**
     * @throws JsonException
     */
    public function toggleValve($meter, $command): bool
    {
        $meter_type = MeterType::find($meter->type_id);
        if (!$meter_type){
            return false;
        }
        if ($meter_type->name === 'Sh Nb-iot' || $meter_type->name === 'Sh Gprs') {
            return $this->toggleShMeter($meter->number, $command);
        }
        if ($meter_type->name === 'Changsha Nb-iot') {
            return $this->toggleChangshaNbIotMeter($meter->number, $command);
        }
        if ($meter_type->name === 'Prepaid' &&$meter->prepaid_meter_type === PrepaidMeterType::HEXING) {
            $hexingMeterService = new HexingMeterService();
            try {
                // Convert ValveStatus enum to string format expected by HexingMeterService
                $valveAction = $command === ValveStatus::OPEN ? 'open' : 'close';
                $hexingMeterService->controlValve([$meter->number], $valveAction);
                return true;

            }catch (Throwable $e){
                Log::error($e);
                return false;
            }
        }
        return false;
    }

    /**
     */
    public function toggleShMeter($meter_number, $command): bool
    {
        $CommandParameter = 153;
        if ($command === ValveStatus::OPEN) {
            $CommandParameter = 85;
        }
        $collection = collect([[
            'MeterId' => $meter_number,
            'CommandType' => 67,
            'CommandParameter' => $CommandParameter
        ]]);
        $status = $this->sendToggleShMeterRequest($collection, env('SH_METER_USERNAME'), env('SH_METER_PASSWORD'));
        if (env('SH_METER_USERNAME_2') && env('SH_METER_PASSWORD_2')){
            $next_status = $this->sendToggleShMeterRequest($collection, env('SH_METER_USERNAME_2'), env('SH_METER_PASSWORD_2'));
            if (!$status){
                $status = $next_status;
            }
        }
        return $status;
    }

    /**
     */
    private function sendToggleShMeterRequest($collection, $username, $password): bool
    {
        $response = Http::retry(3, 3000)
            ->post('http://47.103.146.199:6071/WebHttpApi_EN/TYPostComm.ashx', [
                'CommandList' => $collection,
                'UserName' => $username,
                'PassWord' => $password
            ]);
        if ($response->successful()) {
            Log::info($response->body());
            return true;
        }
        return false;
    }

    /**
     * @throws JsonException
     */
    public function toggleChangshaNbIotMeter($meter_number, $command): bool
    {
        if ($token = $this->loginChangshaNbIot()) {
            $response = Http::asForm()->withHeaders([
                'Token' => $token,
            ])
                ->retry(3, 300)
                ->post('http://220.248.173.29:10004/collect/v3/switchMeter', [
                    'meterNo' => $meter_number,
                    'command' => $command,
                ]);
            if ($response->successful()) {
                Log::error($response->body());
                return true;
            }

        }
        return false;
    }
}
