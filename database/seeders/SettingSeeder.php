<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        Setting::create([
            'key' => 'bill_due_days',
            'value' => 5
        ]);
        Setting::create([
            'key' => 'delay_meter_reading_sms',
            'value' => true,
        ]);
        Setting::create([
            'key' => 'meter_reading_sms_delay_days',
            'value' => 2
        ]);
    }
}
