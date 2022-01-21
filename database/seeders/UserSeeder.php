<?php

namespace Database\Seeders;

use App\Models\User;
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
        $role = Role::create(['name' => 'admin']);
        $user->assignRole($role);
        $user->save();

        $user = new User();
        $user->name = 'Augustine Wafula';
        $user->email = 'augustinetreezy@gmail.com';
        $user->password = bcrypt('password');
        $role = Role::create(['name' => 'user']);
        $user->assignRole($role);
        $user->save();
    }
}
