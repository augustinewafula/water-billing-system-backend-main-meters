<?php

namespace App\Console\Commands;

use App\Jobs\SendMeterReadingsToUser;
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
        SendMeterReadingsToUser::dispatch();
        return 0;
    }
}
