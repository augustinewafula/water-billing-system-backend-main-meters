<?php

namespace App\Traits;

use App\Models\MeterCharge;
use App\Models\ServiceCharge;
use App\Models\User;

trait CalculatesBill
{
    public function calculateBill($previous_reading, $current_reading, $user): float
    {
        if ($user->use_custom_charges_for_cost_per_unit && $user->cost_per_unit > 0){
            $cost_per_unit = $user->cost_per_unit;
        }else {
            $meter_charges = MeterCharge::where('for', 'post-pay')
                ->first();
            $cost_per_unit = $meter_charges->cost_per_unit;
        }
        $units_consumed = $current_reading - $previous_reading;
        $bill = $units_consumed * $cost_per_unit;

        return round($bill);
    }

    private function calculateUnits($amount_paid, $user): float
    {
        $cost_per_unit = $this->getCostPerUnit($user);
        $final_amount = $this->getUserAmountAfterServiceFeeDeduction($amount_paid, $user);

        return round($final_amount / $cost_per_unit, 1);
    }

    private function getUserAmountAfterServiceFeeDeduction($amount_paid, $user): float
    {
        return $amount_paid - $this->calculateServiceFee($user, $amount_paid, 'prepay');
    }

    private function getCostPerUnit($user): float
    {
        if ($user->use_custom_charges_for_cost_per_unit && $user->cost_per_unit > 0){
            $cost_per_unit = $user->cost_per_unit;
        }else {
            $meter_charges = MeterCharge::where('for', 'prepay')
                ->first();
            $cost_per_unit = $meter_charges->cost_per_unit;
        }
        return $cost_per_unit;
    }

    private function calculateServiceFee(User $user, $amount_paid, $for): float
    {
        if ($user->use_custom_charges_for_service_charge && $user->service_charge > 0) {
            return $user->service_charge;
        }
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
        $service_fee = 0;
        foreach ($service_charges as $service_charge) {
            if (($service_charge->from <= $amount_paid) && ($amount_paid <= $service_charge->to)) {
                $service_fee = $service_charge->amount;
                break;
            }
        }
        if ($isServiceChargeInPercentage) {
            if ($service_fee === 0) {
                $service_fee = 1;
            }
            $service_fee = ($service_fee * $amount_paid) / 100;
        }
        return $service_fee;
    }
}
