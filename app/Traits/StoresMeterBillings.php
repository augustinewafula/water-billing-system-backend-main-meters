<?php

namespace App\Traits;

use App\Enums\PaymentStatus;
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

trait StoresMeterBillings
{
    /**
     * @param CreateMeterBillingRequest $request
     * @param $pending_meter_readings
     * @param $user
     * @param $mpesa_transaction_id
     * @param $user_total_amount
     * @return void
     * @throws Throwable
     */
    public function processMeterBillings(CreateMeterBillingRequest $request, $pending_meter_readings, $user, $mpesa_transaction_id, $user_total_amount): void
    {
        $amount_paid = $request->amount_paid;
        $credit_applied = 0;
        if ($request->deductions->monthly_service_charge_deducted > 0 || $request->deductions->connection_fee_deducted > 0 || $request->deductions->unaccounted_debt_deducted > 0) {
            $deductions_sum = $request->deductions->monthly_service_charge_deducted + $request->deductions->connection_fee_deducted + $request->deductions->unaccounted_debt_deducted;
            $credit_applied = $amount_paid - $deductions_sum;
            $amount_paid = 0;
        }

        foreach ($pending_meter_readings as $pending_meter_reading) {
            Log::info('Processing meter billing for meter reading: '. $pending_meter_reading->id);
            $user = $user->refresh();

            $bill_to_pay = $pending_meter_reading->bill;
            if ($pending_meter_reading->status === PaymentStatus::PARTIALLY_PAID) {
                $bill_to_pay = MeterBilling::where('meter_reading_id', $pending_meter_reading->id)
                    ->latest()
                    ->first()
                    ->balance;
            }
            $balance = $bill_to_pay - $user_total_amount;

            if (round($user_total_amount) <= 0.00) {
                Log::info("User total amount is $user_total_amount. No need to create meter billing.");
                break;
            }

            if ($this->saveBillingInfo(
                $amount_paid,
                $balance,
                $user,
                $request->deductions,
                $pending_meter_reading,
                $credit_applied,
                $mpesa_transaction_id,
                $user_total_amount)) {
                    $user_total_amount -= $bill_to_pay;
                    $amount_paid = 0;
                    $request->deductions->monthly_service_charge_deducted = 0;
                    $request->deductions->connection_fee_deducted = 0;
                    $request->deductions->unaccounted_debt_deducted = 0;
                    $credit_applied = 0;
            }

        }
    }

    /**
     * @param $amount_paid
     * @param $balance
     * @param $user
     * @param $deductions
     * @param $meter_reading
     * @param $credit_applied
     * @param $mpesa_transaction_id
     * @param $user_total_amount
     * @return bool
     * @throws Throwable
     */
    public function saveBillingInfo(
        $amount_paid,
        $balance,
        $user,
        $deductions,
        $meter_reading,
        $credit_applied,
        $mpesa_transaction_id,
        $user_total_amount): bool
    {
        try {
            DB::beginTransaction();
            $meter = Meter::find($meter_reading->meter_id);
                $meter->update([
                    'last_billing_date' => Carbon::now()->toDateTimeString(),
                ]);
                $user_bill_balance = $balance;
                $amount_over_paid = 0;
                if ($user->account_balance > 0) {
                    $credit_applied += $user->account_balance;
                }

                if ($this->userHasFullyPaid($balance)) {
                    $user->update([
                        'account_balance' => abs($balance),
                        'last_mpesa_transaction_id' => $mpesa_transaction_id
                    ]);
                    if ($this->userHasOverPaid($balance)) {
                        $amount_over_paid = abs($balance);
                        $meter_reading->update([
                            'status' => PaymentStatus::OVER_PAID,
                        ]);
                    } else {
                        $meter_reading->update([
                            'status' => PaymentStatus::PAID,
                        ]);
                    }
                    $user_bill_balance = 0;
                } else {
                    if ($user->account_balance < 0) {
                        $user->account_balance += $user_total_amount;
                    }else {
                        $user->account_balance = -$balance;
                    }
                    $user->last_mpesa_transaction_id = $mpesa_transaction_id;
                    $user->save();
                    $meter_reading->update([
                        'status' => PaymentStatus::PARTIALLY_PAID,
                    ]);
                }
                MeterBilling::updateOrCreate([
                    'meter_reading_id' => $meter_reading->id,
                    'amount_paid' => $amount_paid,
                    'amount_over_paid' => $amount_over_paid,
                    'balance' => $user_bill_balance,
                    'monthly_service_charge_deducted' => $deductions->monthly_service_charge_deducted,
                    'connection_fee_deducted' => $deductions->connection_fee_deducted,
                    'unaccounted_debt_deducted' => $deductions->unaccounted_debt_deducted,
                    'credit' => $credit_applied,
                    'date_paid' => Carbon::now()->toDateTimeString(),
                    'mpesa_transaction_id' => $mpesa_transaction_id
                ]);
            $bill_month_name = Str::lower(Carbon::createFromFormat('Y-m-d', $meter_reading->month)->format('M'));
            $bill_year = Carbon::createFromFormat('Y-m-d', $meter_reading->month)->format('Y');
            $this->saveMeterBillingReport([
                'meter_id' => $meter->id,
                $bill_month_name => $user_bill_balance,
                'year' => $bill_year,
            ]);
            if ($mpesa_transaction_id){
                MpesaTransaction::find($mpesa_transaction_id)->update([
                    'Consumed' => true,
                ]);
            }
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
