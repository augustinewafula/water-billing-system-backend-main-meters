<?php

namespace Database\Seeders;

use App\Models\MeterCharge;
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
        MeterCharge::create([
            'cost_per_unit' => 130,
            'service_charge' => 200
        ]);
    }
}
