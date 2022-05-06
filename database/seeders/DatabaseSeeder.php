<?php

namespace Database\Seeders;

use App\Models\ConnectionFeeCharge;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            MeterTypeSeeder::class,
            MeterStationSeeder::class,
            MeterChargeSeeder::class,
            SettingSeeder::class,
            AlertContactSeeder::class,
            ConnectionFeeChargeSeeder::class,
        ]);
    }
}
