<?php

namespace App\Console\Commands;

use App\Actions\GenerateMonthlyServiceChargeAction;
use App\Enums\PaymentStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyServiceCharges extends Command
{
    protected $signature = 'charges:generate-monthly';

    protected $description = 'Generate monthly service charges for all eligible users at the beginning of the month';

    public function handle(GenerateMonthlyServiceChargeAction $generateMonthlyServiceChargeAction): void
    {
        $this->info('🔄 Starting monthly service charge generation...');

        $month = Carbon::now()->startOfMonth()->toDateString();

        $totalProcessed = 0;
        $totalCreated = 0;
        $totalSkipped = 0;

        // Process users in chunks
        User::where('should_pay_monthly_service_charge', true)
            ->chunk(100, function ($users) use ($generateMonthlyServiceChargeAction, $month, &$totalProcessed, &$totalCreated, &$totalSkipped) {
                foreach ($users as $user) {
                    $totalProcessed++;

                    $alreadyExists = $user->monthlyServiceCharges()
                        ->whereDate('month', $month)
                        ->exists();

                    if ($alreadyExists) {
                        $this->line("⏭️ Skipped: Charge for user {$user->id} already exists for {$month}.");
                        Log::info("MonthlyServiceCharge skipped: already exists for user {$user->id} ({$month})");
                        $totalSkipped++;
                        continue;
                    }

                    try {
                        $generateMonthlyServiceChargeAction->execute([
                            'user_id' => $user->id,
                            'service_charge' => $user->monthly_service_charge,
                            'month' => $month,
                            'status' => PaymentStatus::NOT_PAID,
                        ]);

                        $this->line("✅ Created: Monthly charge for user {$user->id} ({$month})");
                        Log::info("MonthlyServiceCharge created for user {$user->id} ({$month})");

                        $totalCreated++;
                    } catch (\Throwable $e) {
                        Log::error("Failed to create MonthlyServiceCharge for user {$user->id}: " . $e->getMessage());
                        $this->error("❌ Error creating charge for user {$user->id}: " . $e->getMessage());
                    }
                }
            });

        $this->info("🎉 Done! Processed: {$totalProcessed}, Created: {$totalCreated}, Skipped: {$totalSkipped}");
        Log::info("MonthlyServiceCharge summary: Processed={$totalProcessed}, Created={$totalCreated}, Skipped={$totalSkipped}");
    }
}
