<?php

namespace App\Console\Commands;

use App\Jobs\SendMeterReadingsToUser;
use App\Models\MeterReading;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMeterReadingsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meter-readings:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sms users their meter readings';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $meter_readings = MeterReading::where('sms_sent', false)
            ->where('send_sms_at', '<=', Carbon::now())
            ->take(10)
            ->get();

        foreach ($meter_readings as $meter_reading) {
            SendMeterReadingsToUser::dispatch($meter_reading);
        }
        return 0;
    }
}
