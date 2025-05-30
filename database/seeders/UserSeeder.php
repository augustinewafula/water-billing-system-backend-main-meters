<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class UserSeeder extends Seeder
{
    public function createRoles(): void
    {

    }


    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run(): void
    {
        $user = new User();
        $user->name = 'George Kimani';
        $user->email = 'george@progressive.co.ke';
        $user->password = bcrypt('qwertyuiop');
        $user->assignRole(Role::findByName('super-admin', 'api'));
        $user->save();

        $user = new User();
        $user->name = 'Augustine Wafula';
        $user->email = 'augustinetreezy@gmail.com';
        $user->password = bcrypt('Treezy32');
        $user->assignRole(Role::findByName('admin', 'api'));
        $user->save();

    }
}
