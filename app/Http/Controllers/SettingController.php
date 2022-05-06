<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\ConnectionFeeCharge;
use App\Models\MeterCharge;
use App\Models\ServiceCharge;
use App\Models\Setting;
use DB;
use Illuminate\Http\JsonResponse;
use Log;
use Throwable;

class SettingController extends Controller
{

    public function __construct()
    {
        $this->middleware('permission:setting-list|setting-edit', ['only' => ['index', 'update']]);
    }

    public function index(): JsonResponse
    {
        $settings = Setting::all();
        $meter_settings = MeterCharge::with('service_charges')
            ->get();
        $connection_fee_charges = ConnectionFeeCharge::with('station')
            ->get();
        return response()->json([
            'settings' => $settings,
            'meter_settings' => $meter_settings,
            'connection_fee_charges' => $connection_fee_charges,
        ]);
    }

    /**
     * @throws Throwable
     */
    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $postpaid_meter_charges = MeterCharge::where('for', 'post-pay')
            ->first();
        $prepaid_meter_charges = MeterCharge::where('for', 'prepay')
            ->first();
        $bill_due_days_setting = Setting::where('key', 'bill_due_days')
            ->first();
        $delay_meter_reading_sms_setting = Setting::where('key', 'delay_meter_reading_sms')
            ->first();
        $meter_reading_sms_delay_days_setting = Setting::where('key', 'meter_reading_sms_delay_days')
            ->first();
        $monthly_service_charge = Setting::where('key', 'monthly_service_charge')
            ->first();
        $connection_fee = Setting::where('key', 'connection_fee')
        ->first();
        $connection_fee_per_month = Setting::where('key', 'connection_fee_per_month')
            ->first();

        try {
            DB::beginTransaction();
            $postpaid_meter_charges->update([
                'cost_per_unit' => $request->postpaid_cost_per_unit,
                'service_charge_in_percentage' => $request->postpaid_service_charge_in
            ]);
            $postpaid_meter_service_charges = json_decode($request->postpaid_service_charge, false, 512, JSON_THROW_ON_ERROR);
            if (count($postpaid_meter_service_charges) === 0) {
                DB::rollBack();
                $response = ['message' => 'Postpaid service charge must contain at least one range of price'];
                return response()->json($response, 422);
            }
            $this->updateServiceCharge($postpaid_meter_charges->id, $postpaid_meter_service_charges);

            $prepaid_meter_charges->update([
                'cost_per_unit' => $request->prepaid_cost_per_unit,
                'service_charge_in_percentage' => $request->prepaid_service_charge_in
            ]);
            $prepaid_meter_service_charges = json_decode($request->prepaid_service_charge, false, 512, JSON_THROW_ON_ERROR);
            if (count($prepaid_meter_service_charges) === 0) {
                DB::rollBack();
                $response = ['message' => 'Prepaid service charge must contain at least one range of price'];
                return response()->json($response, 422);
            }
            $this->updateServiceCharge($prepaid_meter_charges->id, $prepaid_meter_service_charges);

            $bill_due_days_setting->update([
                'value' => $request->bill_due_days
            ]);
            $meter_reading_sms_delay_days_setting->update([
                'value' => $request->meter_reading_sms_delay_days
            ]);
            $delay_meter_reading_sms_setting->update([
                'value' => $request->delay_meter_reading_sms
            ]);
            $monthly_service_charge->update([
                'value' => $request->monthly_service_charge
            ]);
            $connection_fee->update([
                'value' => $request->connection_fee
            ]);
            $connection_fee_per_month->update([
                'value' => $request->connection_fee_per_month
            ]);
            DB::commit();
            return response()->json('updated');
        } catch (Throwable $throwable) {
            DB::rollBack();
            Log::error($throwable);
            $response = ['message' => 'Something went wrong, please try again later'];
            return response()->json($response, 422);
        }
    }

    public function updateServiceCharge($meter_charge_id, $service_charges): void
    {
        ServiceCharge::where('meter_charge_id', $meter_charge_id)
            ->forceDelete();
        foreach ($service_charges as $service_charge) {
            ServiceCharge::create([
                'from' => $service_charge->from,
                'to' => $service_charge->to,
                'amount' => $service_charge->amount,
                'meter_charge_id' => $meter_charge_id
            ]);
        }
    }
}
