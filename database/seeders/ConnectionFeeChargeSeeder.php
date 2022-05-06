<?php

namespace Database\Seeders;

use App\Models\ConnectionFee;
use App\Models\ConnectionFeeCharge;
use App\Models\MeterStation;
use Illuminate\Database\Seeder;

class ConnectionFeeChargeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $meter_stations = MeterStation::latest()->get();
        foreach ($meter_stations as $meter_station){
            ConnectionFeeCharge::create([
                'connection_fee' => 25000,
                'connection_fee_monthly_installment' => 200,
                'station_id' => $meter_station->id
            ]);
        }
    }
}
