<?php

namespace App\Jobs;

use App\Enums\MeterMode;
use App\Enums\MeterReadingStatus;
use App\Enums\ValveStatus;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Traits\ToggleValveStatus;
use DB;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JsonException;
use Log;
use Throwable;

class SwitchOffUnpaidMeters implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, ToggleValveStatus;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws JsonException|Throwable
     */
    public function handle(): void
    {
        $unpaid_meters = MeterReading::with(['meter' => function ($query) {
            $query->whereValveStatus(ValveStatus::Open)
                ->orWhere('valve_status', null);
        }])
            ->where('bill_due_at', '>=', now())
            ->where(function ($query) {
                $query->whereStatus(MeterReadingStatus::NotPaid)
                    ->orWhere('status', MeterReadingStatus::Balance);
            })
            ->get();
        Log::info($unpaid_meters);

        foreach ($unpaid_meters as $unpaid_meter) {
            try {
                if ($unpaid_meter->meter->mode === MeterMode::Manual) {
                    //TODO:: Decide what to do with unpaid manual meters
                    continue;
                }
                DB::beginTransaction();
                $meter = Meter::with('user', 'station')->findOrFail($unpaid_meter->meter->id);
                $meter->update([
                    'valve_status' => ValveStatus::Closed,
                ]);
                $this->toggleValve($meter, ValveStatus::Closed);
                DB::commit();
                if (!$meter->user) {
                    continue;
                }
                $paybill_number = $meter->station->paybill_number;
                $account_number = $meter->user->account_number;
                $first_name = explode(' ', trim($meter->user->name))[0];
                //TODO:: Include total debt required to be paid on message
                $message = "Hello $first_name, your water meter has been switched off due to late payment. \nPay via paybill number $paybill_number, account number $account_number";
                SendSMS::dispatch($meter->user->phone, $message, $meter->user->id);
            } catch (Throwable $th) {
                DB::rollBack();
                Log::error($th);
            }
        }
    }
}
