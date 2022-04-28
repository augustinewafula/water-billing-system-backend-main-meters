<?php

namespace App\Console\Commands;

use App\Jobs\CheckFaultyMeter;
use Illuminate\Console\Command;

class CheckFaultyMeterCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meters:check-faulty';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks for faulty meters';

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
        CheckFaultyMeter::dispatch();
        return 0;
    }
}
