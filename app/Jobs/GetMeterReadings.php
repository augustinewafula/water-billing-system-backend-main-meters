<?php

namespace App\Jobs;

use App\Http\Requests\CreateMeterReadingRequest;
use App\Models\Meter;
use App\Traits\StoreMeterReading;
use Carbon\Carbon;
use Http;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;
use Log;

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
     * @throws \Throwable
     */
    public function handle(): void
    {
        $this->getChangshaNbIotMeterReadings();
    }

    /**
     * @throws JsonException
     * @throws \Throwable
     */
    public function getChangshaNbIotMeterReadings(): void
    {
        if ($token = $this->loginChangshaNbIot()){
            $response = Http::withHeaders([
                    'Token' => $token,
                ])
                ->retry(3, 100)
                ->get('http://220.248.173.29:10004/collect/v3/getMeterDataList', [
                    'pageIndex' => 1,
                    'pageSize' => 2000000,
                ]);
            if ($response->successful()) {
//                Log::info('response:' . $response->body());
                $meters =  json_decode($response->body(), false, 512, JSON_THROW_ON_ERROR)->body->recArr;
                foreach ($meters as $key => $meter){
                    $meter_number = $meter->meterno;
                    $database_meter = Meter::where('number', $meter_number)->first();
                    if($database_meter){
                        $request = new CreateMeterReadingRequest();
                        $request->setMethod('POST');
                        $request->request->add([
                            'meter_id' => $database_meter->id,
                            'current_reading' => round($meter->lasttotalall),
                            'month' => Carbon::now()->format('Y-m')
                        ]);
                        $this->store($request);
                        $database_meter->update([
                            'last_reading_date' => Carbon::now()->toDateTimeString()
                        ]);
                    }
                }
            }
        }

    }


    /**
     * @throws JsonException
     */
    public function loginChangshaNbIot(): ?String
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
}
