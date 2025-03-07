<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Jobs\SendMeterDisconnectionRemainder as SendDisconnectionJob;
use App\Models\MeterReading;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

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
        $unpaidMeters = MeterReading::with('meter.user', 'meter.station')
            ->where('disconnection_remainder_sms_sent', false)
            ->where('bill_due_at', '<=', now())
            ->where('created_at', '>=', now()->subWeeks(3)) //To prevent sending old reminders
            ->where(function ($query) {
                $query->whereStatus(PaymentStatus::NOT_PAID)
                    ->orWhere('status', PaymentStatus::PARTIALLY_PAID);
            })
            ->get();

        $delayInterval = 5; // Delay in seconds for each batch of 3 jobs
        $counter = 0;

        foreach ($unpaidMeters as $unpaidMeter) {
            if (!$unpaidMeter->meter || !$unpaidMeter->meter->user) {
                continue;
            }

            // Calculate delay based on batch size of 3
            $delay = intdiv($counter, 3) * $delayInterval;

            SendDisconnectionJob::dispatch($unpaidMeter)->delay(now()->addSeconds($delay));
            $counter++;
        }

        $this->info("Scheduled {$counter} disconnection reminder jobs.");

        return 0;
    }
}
