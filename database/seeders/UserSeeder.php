<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = new User();
        $user->name = 'George Kimani';
        $user->email = 'george@progressive.co.ke';
        $user->password = bcrypt('qwertyuiop');
        $user->first_bill = Carbon::now()->isoFormat('YYYY-MM');
        $admin = Role::create(['name' => 'admin']);
        $user->assignRole($admin);
        $user->save();

        $user = new User();
        $user->name = 'John Doe';
        $user->phone = '07459632142';
        $user->password = bcrypt('aU4v5UXygwEcQDqS');
        $user->first_bill = Carbon::now()->isoFormat('YYYY-MM');
        $user->assignRole(Role::create(['name' => 'user']));
        $user->save();
    }
}
