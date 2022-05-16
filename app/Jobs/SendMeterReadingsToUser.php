<?php

namespace App\Jobs;

use App\Models\MeterReading;
use App\Traits\NotifiesOnJobFailure;
use App\Traits\SendsMeterReading;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMeterReadingsToUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SendsMeterReading, NotifiesOnJobFailure;

    public $tries = 3;

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
     */
    public function handle(): void
    {
        $meter_readings = MeterReading::where('sms_sent', false)
            ->where('send_sms_at', '<=', Carbon::now())
            ->get();

        foreach ($meter_readings as $meter_reading) {
            $this->sendMeterReading($meter_reading);
        }
    }
}
