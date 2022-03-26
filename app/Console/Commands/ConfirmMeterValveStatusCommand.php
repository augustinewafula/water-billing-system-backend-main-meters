<?php

namespace App\Console\Commands;

use App\Jobs\ConfirmMeterValveStatus;
use Illuminate\Console\Command;

class ConfirmMeterValveStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meters:confirm-valve-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks if database meter valve status matches that from api';

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
        ConfirmMeterValveStatus::dispatch();
        return 0;
    }
}
