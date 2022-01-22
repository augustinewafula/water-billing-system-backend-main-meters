<?php

namespace Database\Seeders;

use App\Enums\MeterStationType;
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
                'type' => MeterStationType::Manual
            ],
            [
                'name' => 'Acc 2',
                'type' => MeterStationType::Manual
            ],
            [
                'name' => 'Acc 3',
                'type' => MeterStationType::Automatic
            ]
        ];
        collect($meterStations)->each(function ($meterStation) {
            MeterStation::create($meterStation);
        });
    }
}
