<?php

namespace Database\Seeders;

use App\Models\MeterCharge;
use App\Models\ServiceCharge;
use Illuminate\Database\Seeder;

class MeterChargeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $meter_charge = MeterCharge::create([
            'cost_per_unit' => 130,
            'for' => 'prepay'
        ]);
        ServiceCharge::create([
            'from' => 1,
            'to' => 500,
            'amount' => 30,
            'meter_charge_id' => $meter_charge->id
        ]);
        ServiceCharge::create([
            'from' => 500,
            'to' => 1000,
            'amount' => 25,
            'meter_charge_id' => $meter_charge->id
        ]);
        ServiceCharge::create([
            'from' => 1000,
            'to' => 3000,
            'amount' => 20,
            'meter_charge_id' => $meter_charge->id
        ]);
        ServiceCharge::create([
            'from' => 3000,
            'to' => 6000,
            'amount' => 15,
            'meter_charge_id' => $meter_charge->id
        ]);
        ServiceCharge::create([
            'from' => 6000,
            'to' => 10000,
            'amount' => 8,
            'meter_charge_id' => $meter_charge->id
        ]);
        ServiceCharge::create([
            'from' => 10000,
            'to' => 100000,
            'amount' => 5,
            'meter_charge_id' => $meter_charge->id
        ]);

        $meter_charge = MeterCharge::create([
            'cost_per_unit' => 200,
            'service_charge_in_percentage' => true,
            'for' => 'post-pay'
        ]);
        ServiceCharge::create([
            'from' => 1,
            'to' => 500000,
            'amount' => 10,
            'meter_charge_id' => $meter_charge->id
        ]);
    }
}
