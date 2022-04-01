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
            [
                'name' => 'Acc 1',
                'location' => 'Juja',
                'paybill_number' => 994470
            ],
            [
                'name' => 'Acc 2',
                'location' => 'Kasarani',
                'paybill_number' => 994470
            ],
            [
                'name' => 'Acc 3',
                'location' => 'Umoja',
                'paybill_number' => 779774
            ]
        ];
        collect($meterStations)->each(function ($meterStation) {
            MeterStation::create($meterStation);
        });
    }
}
