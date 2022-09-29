<?php

namespace App\Console\Commands;

use App\Models\ConnectionFee;
use App\Services\ConnectionFeeService;
use Illuminate\Console\Command;
use Throwable;

class DebitConnectionFeeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:debit-connection-fee';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debit monthly connection fee of current month for all users who have not yet fully paid.';

    /**
     * Execute the console command.
     *
     * @param ConnectionFeeService $connectionFeeService
     * @return int
     * @throws Throwable
     */
    public function handle(ConnectionFeeService $connectionFeeService): int
    {
        $connectionFees = ConnectionFee::notAddedToUserTotalDebt()
            ->whereDate('month', '<=', now())
            ->notPaid()
            ->orWhere
            ->hasBalance()
            ->get();
        \Log::info('Found ' . $connectionFees->count() . ' connection fees to debit.');
        foreach ($connectionFees as $connectionFee) {
            $connectionFeeService->addConnectionFeeBillToUserAccount($connectionFee->id);
        }
        return 0;
    }
}
