<?php

namespace App\Jobs;

use App\Models\MeterReading;
use App\Traits\NotifiesOnJobFailure;
use App\Traits\SendsMeterReading;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMeterReadingsToUser implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, SendsMeterReading, NotifiesOnJobFailure;

    public $tries = 2;
    public $failOnTimeout = true;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($meter_reading)
    {
        //
    }

    /**
     * The meter_reading instance.
     *
     * @var MeterReading
     */
    public $meter_reading;

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public $uniqueFor = 1200;

    /**
     * The unique ID of the job.
     *
     * @return string
     */
    public function uniqueId(): string
    {
        return $this->meter_reading->id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {

        $this->sendMeterReading($this->meter_reading);
    }
}
