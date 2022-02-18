<?php

namespace App\Jobs;

use App\Http\Requests\CreateMeterReadingRequest;
use App\Models\Meter;
use App\Traits\StoreMeterReading;
use Carbon\Carbon;
use DB;
use Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;
use Log;
use Throwable;

class GetMeterReadings implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, StoreMeterReading;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws JsonException
     * @throws Throwable
     */
    public function handle(): void
    {
        $this->getChangshaNbIotMeterReadings();
        $this->getShMeterReadings();
    }

    /**
     * @throws JsonException
     * @throws Throwable
     */
    public function getShMeterReadings(): void
    {
        $database_meter_numbers = $this->getShDatabaseMeters();
        $response = Http::retry(3, 100)
            ->post('http://47.103.146.199:6071/WebHttpApi_EN/TYGetMeterData.ashx', [
                'MeterIdList' => $database_meter_numbers,
                'UserName' => env('SH_METER_USERNAME'),
                'PassWord' => env('SH_METER_PASSWORD')
            ]);
        if ($response->successful()) {
            $meters = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->MeterDataList;
            foreach ($meters as $meter) {
                $meter_number = $meter->MeterId;
                $meter_reading = $meter->PositiveCumulativeFlow;
                $this->saveMeterReadings($meter_number, $meter_reading);
            }
        }
    }

    /**
     * @throws JsonException
     * @throws Throwable
     */
    public function getChangshaNbIotMeterReadings(): void
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
                $meters = json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->body->recArr;
                foreach ($meters as $key => $meter) {
                    $meter_number = $meter->meterno;
                    $meter_reading = $meter->lasttotalall;
                    $this->saveMeterReadings($meter_number, $meter_reading);
                }
            }
        }

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
     */
    public function loginChangshaNbIot(): ?string
    {
        $response = Http::retry(3, 100)
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

    /**
     * @param $meter_number
     * @param $meter_reading
     * @return void
     * @throws Throwable
     */
    public function saveMeterReadings($meter_number, $meter_reading): void
    {
        $database_meter = Meter::where('number', $meter_number)->first();
        if ($database_meter) {
            $request = new CreateMeterReadingRequest();
            $request->setMethod('POST');
            $request->request->add([
                'meter_id' => $database_meter->id,
                'current_reading' => round($meter_reading),
                'month' => Carbon::now()->format('Y-m')
            ]);
            try {
                DB::transaction(function () use ($request, $database_meter) {
                    $this->store($request);
                    $database_meter->update([
                        'last_reading_date' => Carbon::now()->toDateTimeString()
                    ]);
                });
            } catch (Throwable $exception) {
                Log::error($exception);
            }
        }
    }
}
