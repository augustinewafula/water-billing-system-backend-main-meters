<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MeterStation;

class MeterStationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $meterStations = [
            'Acc 1',
            'Acc 2',
            'Acc 3'
        ];
        foreach ($meterStations as $value){
            $MeterStation = new MeterStation();
            $MeterStation->name = $value;
            $MeterStation->save();
        }
    }
}
