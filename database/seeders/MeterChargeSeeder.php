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
            'cost_per_unit' => 200,
            'service_charge_in_percentage' => true,
            'for' => 'prepay'
        ]);
        ServiceCharge::create([
            'from' => 1,
            'to' => 500000,
            'amount' => 0,
            'meter_charge_id' => $meter_charge->id
        ]);


        $meter_charge = MeterCharge::create([
            'cost_per_unit' => 130,
            'for' => 'post-pay'
        ]);
        ServiceCharge::create([
            'from' => 1,
            'to' => 500000,
            'amount' => 0,
            'meter_charge_id' => $meter_charge->id
        ]);
    }
}
