<?php

namespace App\Console\Commands;

use App\Jobs\SwitchOffUnpaidMeters;
use Illuminate\Console\Command;

class SwitchOffUnpaidMetersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meters:switch-off-unpaid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Switch off valve of unpaid meters';

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
        SwitchOffUnpaidMeters::dispatch();
        return 0;
    }
}
