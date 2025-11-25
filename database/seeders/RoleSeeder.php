<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        Role::create(['name' => 'super_admin']);
        Role::create(['name' => 'organisation_super_admin']);
        Role::create(['name' => 'organisation_admin']);
        Role::create(['name' => 'organisation_user']);
        Role::create(['name' => 'basecamp']);
    }
}
