<?php

namespace App\Jobs;

use App\Enums\MeterMode;
use App\Enums\MeterReadingStatus;
use App\Enums\ValveStatus;
use App\Models\MeterReading;
use App\Traits\NotifiesOnJobFailure;
use App\Traits\TogglesValveStatus;
use Carbon\Carbon;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;
use Throwable;

class SwitchOnPaidMeter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TogglesValveStatus, NotifiesOnJobFailure;

    protected $meter;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($meter)
    {
        $this->meter = $meter;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws Throwable
     */
    public function handle(): void
    {
        $month_ago = Carbon::now()->subtract(1, 'month')->format('Y-m');
        $meter = MeterReading::with(['meter' => function ($query) {
                $query->whereValveStatus(ValveStatus::Closed)
                    ->orWhere('valve_last_switched_off_by', 'system');
            }])->where('meter_id', $this->meter->id)
            ->where('month', '>=', $month_ago)
            ->whereStatus(MeterReadingStatus::Paid)
            ->latest()
            ->limit(1)
            ->first();
        if (!$meter) {
            return;
        }

        try {
            DB::beginTransaction();
            if ($this->meter->mode !== MeterMode::Manual) {
                $this->toggleValve($meter, ValveStatus::Open);
            }
            $this->meter->update([
                'valve_status' => ValveStatus::Open,
            ]);
            DB::commit();

        } catch (Throwable $th) {
            DB::rollBack();
            Log::error($th);
        }
    }
}
