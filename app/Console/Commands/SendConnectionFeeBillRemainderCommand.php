<?php

namespace App\Console\Commands;

use App\Jobs\SendConnectionFeeBillRemainder;
use Illuminate\Console\Command;

class SendConnectionFeeBillRemainderCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'connection-fees:send-bill-remainder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send connection fee bill remainder to customers';

    /**
     * Execute the console command.
     *X
     * @return int
     */
    public function handle(): int
    {
        SendConnectionFeeBillRemainder::dispatch();
        return 0;
    }
}
