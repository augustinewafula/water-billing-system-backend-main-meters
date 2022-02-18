<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GetMeterReadings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meter-readings:get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch meter readings from api and save to database';

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
    public function handle()
    {
        \App\Jobs\GetMeterReadings::dispatch();
        return 0;
    }
}
