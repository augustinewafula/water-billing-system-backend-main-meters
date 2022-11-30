<?php

namespace Database\Seeders;

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
            SettingSeeder2::class,
            SettingSeeder3::class,
            AlertContactSeeder::class,
            PermissionSeeder2::class,
        ]);
    }
}
