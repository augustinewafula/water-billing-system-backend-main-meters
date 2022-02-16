<?php

namespace App\Http\Controllers;

use App\Enums\MeterReadingStatus;
use App\Http\Requests\CreateMeterBillingRequest;
use App\Models\Meter;
use App\Models\MeterBilling;
use App\Models\MeterBillingReport;
use App\Models\MeterReading;
use App\Models\User;
use Carbon\Carbon;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Log;
use Str;
use Throwable;

class MeterBillingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param CreateMeterBillingRequest $request
     * @return JsonResponse
     */
    public function store(CreateMeterBillingRequest $request): JsonResponse
    {
        $pending_meter_readings = MeterReading::where('meter_id', $request->meter_id)
            ->where(function ($query) {
                $query->where('status', MeterReadingStatus::NotPaid);
                $query->orWhere('status', MeterReadingStatus::Balance);
            })
            ->orderBy('created_at', 'ASC')->get();

        foreach ($pending_meter_readings as $pending_meter_reading) {
            $user = User::where('meter_id', $request->meter_id)->first();

            if (!$user) {
                //save to unresolved money
                break;
            }

            $user_total_amount = $request->amount_paid;
            if ($user->account_balance > 0) {
                $user_total_amount += $request->amount_paid;
            }

            $bill_to_pay = $pending_meter_reading->bill;
            if ($pending_meter_reading->status === MeterReadingStatus::Balance) {
                $bill_to_pay = MeterBilling::where('meter_reading_id', $pending_meter_reading->id)->first()->balance;
            }
            $balance = $bill_to_pay - $user_total_amount;
            $this->saveBillingInfo(
                $user->account_balance,
                $request->amount_paid,
                $balance,
                $user,
                $pending_meter_reading);

        }

        return response()->json('created', 201);
    }

    public function userHasFullyPaid($balance): bool
    {
        return $balance <= 0;
    }

    /**
     * Display the specified resource.
     *
     * @param MeterBilling $meterBilling
     * @return Response
     */
    public function show(MeterBilling $meterBilling)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param MeterBilling $meterBilling
     * @return Response
     */
    public function update(Request $request, MeterBilling $meterBilling)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param MeterBilling $meterBilling
     * @return Response
     */
    public function destroy(MeterBilling $meterBilling)
    {
        //
    }

    /**
     * @param $user_account_balance
     * @param $amount_paid
     * @param $balance
     * @param $user
     * @param $meter_reading
     * @return bool
     */
    public function saveBillingInfo($user_account_balance,
                                    $amount_paid,
                                    $balance,
                                    $user,
                                    $meter_reading): bool
    {
        try {
            DB::transaction(function () use ($user_account_balance, $amount_paid, $balance, $user, $meter_reading) {
                $meter = Meter::find($meter_reading->meter_id);
                $meter->update([
                    'last_billing_date' => Carbon::now()->toDateTimeString(),
                ]);
                $user_bill_balance = $balance;
                if ($this->userHasFullyPaid($balance)) {
                    $new_user_balance = $user_account_balance + abs($balance);
                    $user->update([
                        'account_balance' => $new_user_balance
                    ]);
                    $meter_reading->update([
                        'status' => MeterReadingStatus::Paid,
                    ]);
                    $user_bill_balance = 0;
                } else {
                    $meter_reading->update([
                        'status' => MeterReadingStatus::Balance,
                    ]);
                }
                MeterBilling::updateOrCreate([
                    'meter_reading_id' => $meter_reading->id,
                    'amount_paid' => $amount_paid,
                    'balance' => $user_bill_balance,
                    'date_paid' => Carbon::now()->toDateTimeString()
                ]);
                $bill_month_name = Str::lower(Carbon::createFromFormat('Y-m', $meter_reading->month)->format('M'));
                $bill_year = Carbon::createFromFormat('Y-m', $meter_reading->month)->format('Y');
                $this->saveMeterBillingReport([
                    'meter_id' => $meter->id,
                    $bill_month_name => $user_bill_balance,
                    'year' => $bill_year,
                ]);
            });
            return true;
        } catch (Throwable $th) {
            Log::error($th);
            return false;
        }
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
