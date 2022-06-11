<?php

namespace App\Jobs;

use App\Enums\MeterMode;
use App\Enums\PaymentStatus;
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

    public $tries = 2;
    public $failOnTimeout = true;

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
        if ($this->meter->valve_status === ValveStatus::Open){
            return;
        }
        try {
            DB::beginTransaction();
            if ($this->meter->mode !== MeterMode::Manual) {
                $this->toggleValve($this->meter, ValveStatus::Open);
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
