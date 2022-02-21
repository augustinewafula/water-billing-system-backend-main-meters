<?php

namespace Database\Seeders;

use App\Models\MeterType;
use Illuminate\Database\Seeder;

class MeterTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $meterTypes = [
            'Sh Gprs',
            'Sh Nb-iot',
            'Changsha Nb-iot',
            'Prepaid'
        ];
        foreach ($meterTypes as $value){
            $meterType = new MeterType();
            $meterType->name = $value;
            $meterType->save();
        }
    }
}
