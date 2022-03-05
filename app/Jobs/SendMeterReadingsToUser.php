<?php

namespace App\Jobs;

use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendMeterReadingsToUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
     */
    public function handle(): void
    {
        $meter_readings = MeterReading::where('sms_sent', false)
            ->where('send_sms_at', '<=', Carbon::now())
            ->get();

        foreach ($meter_readings as $meter_reading) {
            $user = User::where('meter_id', $meter_reading->meter_id)
                ->first();
            if (!$user) {
                break;
            }
            $meter = Meter::find($meter_reading->meter_id);
            $user_name = ucwords($user->name);
            $due_date = Carbon::parse($meter_reading->bill_due_at)->format('d/m/Y');
            $bill_month = Carbon::parse($meter_reading->created_at)->isoFormat('MMMM YYYY');
            $units_consumed = $meter_reading->current_reading - $meter_reading->previous_reading;
            $message = "Hello $user_name, your water billing for $bill_month is as follows:\nReading: $meter_reading->current_reading\nPrevious reading: $meter_reading->previous_reading\nUnits consumed: $units_consumed\nBill: Ksh $meter_reading->bill\nBalance brought forward: Ksh $user->account_balance\nDue date: $due_date\nPay via paybill number 994470, account number $meter->number";
            SendSMS::dispatch($user->phone, $message);
            $meter_reading->update([
                'sms_sent' => true,
            ]);
        }
    }
}
