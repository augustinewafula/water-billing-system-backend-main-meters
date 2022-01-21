<?php

namespace Database\Seeders;

use App\Models\MeterStation;
use Illuminate\Database\Seeder;

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
