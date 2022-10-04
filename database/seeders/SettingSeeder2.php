<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder2 extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {

        Setting::create([
            'key' => 'send_connection_fee_bill_remainder_sms',
            'value' => false,
        ]);
        Setting::create([
            'key' => 'days_before_sending_connection_fee_bill_remainder_sms',
            'value' => 5
        ]);
    }
}
