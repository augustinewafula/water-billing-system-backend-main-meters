<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Jobs\SendMeterDisconnectionRemainder as SendDisconnectionJob;
use App\Models\MeterReading;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SendMeterDisconnectionRemainder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meters:send-disconnection-remainder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends meter disconnection reminders to users with unpaid bills';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        Log::info('Meter Disconnection Reminder Command Started.');

        $unpaidMeters = MeterReading::with('meter.user', 'meter.station')
            ->where('disconnection_remainder_sms_sent', false)
            ->where('bill_due_at', '<=', now())
            ->where('created_at', '>=', now()->subWeeks(3)) // Prevent old reminders
            ->where(function ($query) {
                $query->whereStatus(PaymentStatus::NOT_PAID)
                    ->orWhere('status', PaymentStatus::PARTIALLY_PAID);
            })
            ->get();

        $totalUnpaidMeters = $unpaidMeters->count();
        Log::info("Found {$totalUnpaidMeters} unpaid meter readings eligible for disconnection reminders.");

        $delayInterval = 5; // Delay in seconds for each batch of 3 jobs
        $counter = 0;

        foreach ($unpaidMeters as $unpaidMeter) {
            if (!$unpaidMeter->meter || !$unpaidMeter->meter->user) {
                Log::warning("Skipped meter ID: {$unpaidMeter->id} due to missing meter or user data.");
                continue;
            }

            $delay = intdiv($counter, 3) * $delayInterval;

            SendDisconnectionJob::dispatch($unpaidMeter)->delay(now()->addSeconds($delay));

            Log::info("Disconnection reminder job scheduled for Meter ID: {$unpaidMeter->id} (User ID: {$unpaidMeter->meter->user->id}) with a delay of {$delay} seconds.");

            $counter++;
        }

        $this->info("Scheduled {$counter} disconnection reminder jobs.");
        Log::info("Scheduled {$counter} disconnection reminder jobs.");

        Log::info('Meter Disconnection Reminder Command Finished.');
        return 0;
    }
}
