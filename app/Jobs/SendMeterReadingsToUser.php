<?php

namespace App\Jobs;

use App\Models\MeterReading;
use App\Traits\GeneratesPassword;
use App\Traits\NotifiesOnJobFailure;
use App\Traits\SendsMeterReading;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMeterReadingsToUser implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SendsMeterReading, NotifiesOnJobFailure, GeneratesPassword;

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
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 600;

    /**
     * The unique ID of the job.
     *
     * @return string
     * @throws Exception
     */
    public function uniqueId(): string
    {
        return $this->generatePassword(5);
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
