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
            'Sh meters Gprs',
            'Sh meters Nb-iot',
            'Changsha Nb-iot'
        ];
        foreach ($meterTypes as $value){
            $meterType = new MeterType();
            $meterType->name = $value;
            $meterType->save();
        }
    }
}
