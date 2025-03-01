<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\MpesaTransaction;
use App\Jobs\GenerateMeterTokenJob;

class GenerateTokenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:token {user_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a meter token for a user';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $userId = $this->argument('user_id');
        $user = User::with('meter')->findOrFail($userId);

        if ($user->account_balance < 0) {
            $this->error('Account balance is insufficient.');
            return Command::FAILURE;
        }

        $deductions = collect([
            'monthly_service_charge_deducted' => 0,
            'unaccounted_debt_deducted' => 0,
            'connection_fee_deducted' => 0,
        ]);

        $mpesaTransaction = MpesaTransaction::where('BillRefNumber', $user->account_number)
            ->latest()
            ->first();

        GenerateMeterTokenJob::dispatch(
            $user->meter->id,
            $mpesaTransaction,
            $deductions,
            $user->account_balance,
            $user
        );

        $this->info('Token generation initiated successfully.');
        return Command::SUCCESS;
    }
}
