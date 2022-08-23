<?php

namespace App\Console\Commands;

use App\Jobs\GetMetersLastCommunicationDate;
use Illuminate\Console\Command;

class GetMetersLastCommunicationDateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meters:get-last-communication-date';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get last communication date of meters';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $this->info('Getting last communication date of meters...');
        GetMetersLastCommunicationDate::dispatch();
        return 0;
    }
}
