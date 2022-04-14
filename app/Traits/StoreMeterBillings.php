<?php

namespace App\Traits;

use App\Enums\MeterReadingStatus;
use App\Http\Requests\CreateMeterBillingRequest;
use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\MeterBillingReport;
use App\Models\MpesaTransaction;
use Carbon\Carbon;
use DB;
use Log;
use Str;
use Throwable;

trait StoreMeterBillings
{
    /**
     * @param CreateMeterBillingRequest $request
     * @param $pending_meter_readings
     * @param $user
     * @param $mpesa_transaction_id
     * @return void
     * @throws Throwable
     */
    public function processMeterBillings(CreateMeterBillingRequest $request, $pending_meter_readings, $user, $mpesa_transaction_id): void
    {
        $monthly_service_charge_deducted = $request->monthly_service_charge_deducted;
        $amount_paid = $request->amount_paid;
        $user_total_amount = $amount_paid;
        if ($monthly_service_charge_deducted > 0) {
            $user_total_amount = 0;
        }

        foreach ($pending_meter_readings as $pending_meter_reading) {
            $user = $user->refresh();
            if ($user->account_balance > 0) {
                $user_total_amount += $user->account_balance;
            }

            $bill_to_pay = $pending_meter_reading->bill;
            if ($pending_meter_reading->status === MeterReadingStatus::Balance) {
                $bill_to_pay = MeterBilling::where('meter_reading_id', $pending_meter_reading->id)
                    ->latest()
                    ->first()
                    ->balance;
            }
            $balance = $bill_to_pay - $user_total_amount;

            if (round($user_total_amount) <= 0.00) {
                break;
            }

            if ($this->saveBillingInfo(
                $amount_paid,
                $balance,
                $user,
                $monthly_service_charge_deducted,
                $pending_meter_reading,
                $mpesa_transaction_id)) {
                $user_total_amount = 0;
                $amount_paid = 0;
                $monthly_service_charge_deducted = 0;
            }

        }
    }

    /**
     * @param $amount_paid
     * @param $balance
     * @param $user
     * @param $monthly_service_charge_deducted
     * @param $meter_reading
     * @param $mpesa_transaction_id
     * @return bool
     * @throws Throwable
     */
    public function saveBillingInfo(
        $amount_paid,
        $balance,
        $user,
        $monthly_service_charge_deducted,
        $meter_reading,
        $mpesa_transaction_id): bool
    {
        try {
            DB::beginTransaction();
            $meter = Meter::find($meter_reading->meter_id);
                $meter->update([
                    'last_billing_date' => Carbon::now()->toDateTimeString(),
                ]);
                $user_bill_balance = $balance;
                $amount_over_paid = 0;
                $credit = 0;
                if ($user->account_balance > 0) {
                    $credit = $user->account_balance;
                }
                if ($this->userHasFullyPaid($balance)) {
                    $user->update([
                        'account_balance' => abs($balance)
                    ]);
                    if ($this->userHasOverPaid($balance)) {
                        $amount_over_paid = abs($balance);
                        $meter_reading->update([
                            'status' => MeterReadingStatus::OverPaid,
                        ]);
                    } else {
                        $meter_reading->update([
                            'status' => MeterReadingStatus::Paid,
                        ]);
                    }
                    $user_bill_balance = 0;
                } else {
                    $user->update([
                        'account_balance' => -$balance
                    ]);
                    $meter_reading->update([
                        'status' => MeterReadingStatus::Balance,
                    ]);
                }
                MeterBilling::updateOrCreate([
                    'meter_reading_id' => $meter_reading->id,
                    'amount_paid' => $amount_paid,
                    'amount_over_paid' => $amount_over_paid,
                    'balance' => $user_bill_balance,
                    'monthly_service_charge_deducted' => $monthly_service_charge_deducted,
                    'credit' => $credit,
                    'date_paid' => Carbon::now()->toDateTimeString(),
                    'mpesa_transaction_id' => $mpesa_transaction_id
                ]);
            $bill_month_name = Str::lower(Carbon::createFromFormat('Y-m', $meter_reading->month)->format('M'));
            $bill_year = Carbon::createFromFormat('Y-m', $meter_reading->month)->format('Y');
            $this->saveMeterBillingReport([
                'meter_id' => $meter->id,
                $bill_month_name => $user_bill_balance,
                'year' => $bill_year,
            ]);
            MpesaTransaction::find($mpesa_transaction_id)->update([
                'Consumed' => true,
            ]);
            DB::commit();
            return true;
        } catch (Throwable $th) {
            DB::rollBack();
            Log::error($th);
            return false;
        }
    }

    public function userHasFullyPaid($balance): bool
    {
        return $balance <= 0;
    }

    public function userHasOverPaid($balance): bool
    {
        return $balance < 0;
    }

    public function saveMeterBillingReport($report): void
    {
        $meter_billing_report = MeterBillingReport::where('meter_id', $report['meter_id'])
            ->where('year', $report['year'])->first();
        if ($meter_billing_report) {
            $meter_billing_report->update($report);
            return;
        }
        MeterBillingReport::create($report);
    }
}
