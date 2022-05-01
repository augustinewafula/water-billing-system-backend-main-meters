<?php

namespace App\Console\Commands;

use App\Jobs\GenerateMonthlyConnectionFee;
use Illuminate\Console\Command;

class GenerateMonthlyConnectionFeeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monthly-connection-fee:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate users monthly service charge';

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
        GenerateMonthlyConnectionFee::dispatch();
        return 0;
    }
}
