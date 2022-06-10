<?php

namespace App\Console\Commands;

use App\Jobs\ProcessTransaction;
use App\Models\MpesaTransaction;
use Illuminate\Console\Command;

class ProcessUnprocessedTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'transactions:process-unprocessed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process unprocessed transactions';

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
        $mpesa_transactions = MpesaTransaction::whereConsumed(false)
            ->get();
        foreach ($mpesa_transactions as $mpesa_transaction){
            ProcessTransaction::dispatch($mpesa_transaction);
        }
        return 0;
    }
}
