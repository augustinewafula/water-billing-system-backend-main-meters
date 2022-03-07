<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingRequest;
use App\Models\MeterCharge;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Log;
use Throwable;

class SettingController extends Controller
{
    public function index(): JsonResponse
    {
        $settings = Setting::all();
        $meter_settings = MeterCharge::all();
        return response()->json([
            'settings' => $settings,
            'meter_settings' => $meter_settings,
        ]);
    }

    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $post_pay_meter_charges = MeterCharge::where('for', 'post-pay')
            ->first();
        $prepay_meter_charges = MeterCharge::where('for', 'prepay')
            ->first();
        $bill_due_days_setting = Setting::where('key', 'bill_due_days')
            ->first();
        $meter_reading_sms_delay_days_setting = Setting::where('key', 'meter_reading_sms_delay_days')
            ->first();

        try {
            $post_pay_meter_charges->update([
                'cost_per_unit' => $request->postpaid_cost_per_unit,
                'service_charge' => $request->postpaid_service_charge,
                'service_charge_in_percentage' => $request->postpaid_service_charge_in
            ]);
            $prepay_meter_charges->update([
                'cost_per_unit' => $request->prepay_cost_per_unit,
                'service_charge' => $request->prepay_service_charge,
                'service_charge_in_percentage' => $request->prepay_service_charge_in
            ]);
            $bill_due_days_setting->update([
                'value' => $request->bill_due_days
            ]);
            $meter_reading_sms_delay_days_setting->update([
                'value' => $request->meter_reading_sms_delay_days
            ]);
            return response()->json('updated');
        } catch (Throwable $throwable) {
            Log::error($throwable);
            $response = ['message' => 'Something went wrong, please try again later'];
            return response()->json($response, 422);
        }
    }
}
