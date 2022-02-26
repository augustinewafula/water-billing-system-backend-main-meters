<?php

namespace App\Traits;

use App\Models\MeterCharge;

trait calculateBill
{
    public function calculateBill($previous_reading, $current_reading): float
    {
        $meter_charges = MeterCharge::where('for', 'prepay')
            ->first();
        $bill = ($current_reading - $previous_reading) * $meter_charges->cost_per_unit;
        $service_charge = $meter_charges->service_charge;
        if ($meter_charges->service_charge_in_percentage) {
            $service_charge = ($service_charge * $bill) / 100;
        }
        return round($bill + $service_charge);
    }

    private function calculateUnits($amount_paid): float
    {
        $meter_charges = MeterCharge::where('for', 'post-pay')
            ->first();

        return round($amount_paid / $meter_charges->cost_per_unit);
    }

    private function calculateServiceFee($amount_paid, $for): float
    {
        $meter_charges = MeterCharge::where('for', $for)
            ->first();
        $service_charge = $meter_charges->service_charge;
        if ($meter_charges->service_charge_in_percentage) {
            $service_charge = ($service_charge * $amount_paid) / 100;
        }

        return round($service_charge);
    }
}
