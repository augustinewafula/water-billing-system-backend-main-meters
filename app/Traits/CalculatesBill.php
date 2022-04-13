<?php

namespace App\Traits;

use App\Models\MeterCharge;
use App\Models\ServiceCharge;

trait calculatesBill
{
    public function calculateBill($previous_reading, $current_reading): float
    {
        $meter_charges = MeterCharge::where('for', 'post-pay')
            ->first();
        $units_consumed = $current_reading - $previous_reading;
        $bill = $units_consumed * $meter_charges->cost_per_unit;

        return round($bill);
    }

    private function calculateUnits($amount_paid): float
    {
        $meter_charges = MeterCharge::where('for', 'prepay')
            ->first();
        $final_amount = $amount_paid - $this->calculateServiceFee($amount_paid, 'prepay');

        return round($final_amount / $meter_charges->cost_per_unit, 1);
    }

    private function calculateServiceFee($amount_paid, $for): float
    {
        $meter_charges = MeterCharge::where('for', $for)
            ->first();
        if ((int)$amount_paid === 0) {
            $amount_paid = 1;
        }
        $service_charge = $this->getServiceCharge($meter_charges->id, $amount_paid, $meter_charges->service_charge_in_percentage);
        return round($service_charge);
    }

    public function getServiceCharge($meter_charge_id, $amount_paid, $isServiceChargeInPercentage): int
    {
        $service_charges = ServiceCharge::where('meter_charge_id', $meter_charge_id)
            ->get();
        $service_fee = 10;
        foreach ($service_charges as $service_charge) {
            if (($service_charge->from <= $amount_paid) && ($amount_paid <= $service_charge->to)) {
                $service_fee = $service_charge->amount;
                break;
            }
        }
        if ($isServiceChargeInPercentage) {
            if ($amount_paid === 1) {
                return 200;
            }
            $service_fee = ($service_fee * $amount_paid) / 100;
        }
        return $service_fee;
    }
}
