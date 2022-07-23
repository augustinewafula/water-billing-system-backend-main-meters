<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Enums\ValveStatus;
use App\Jobs\SwitchOnPaidMeter;
use App\Models\Meter;
use App\Models\MeterReading;
use Illuminate\Console\Command;

class SwitchOnPaidMetersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'meters:switch-on-paid';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Switch on valve of unpaid meters';

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
        $paid_meters = MeterReading::with(['meter' => function ($query) {
                $query->whereValveStatus(ValveStatus::CLOSED);
            }])
            ->whereDate('month', '>=', now()->subMonth()->startOfMonth()->startOfDay())
            ->where(function ($query) {
                $query->whereStatus(PaymentStatus::PAID)
                    ->orWhere('status', PaymentStatus::OVER_PAID);
            })
            ->get();

        foreach ($paid_meters as  $paid_meter){
            if (!$paid_meter->meter){
                continue;
            }
            SwitchOnPaidMeter::dispatch(Meter::find($paid_meter->meter->id));
            \Log::info('Auto switching on paid meter id: '. $paid_meter->meter->id);
        }
        return 0;
    }
}
