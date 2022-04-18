<?php

namespace Database\Seeders;

use App\Enums\AlertContactTypes;
use App\Models\AlertContact;
use App\Models\User;
use Illuminate\Database\Seeder;

class AlertContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $admins = User::role(['admin', 'super-admin'])->get();

        foreach ($admins as $admin){
            AlertContact::create([
                'user_id' => $admin->id,
                'type' => AlertContactTypes::Email,
                'value'=> $admin->email
            ]);
        }
    }
}
