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
                'name' => 'Acc 1'
            ],
            [
                'name' => 'Acc 2'
            ],
            [
                'name' => 'Acc 3'
            ]
        ];
        collect($meterStations)->each(function ($meterStation) {
            MeterStation::create($meterStation);
        });
    }
}
