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
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'organisation_super_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'organisation_admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'organisation_user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'basecamp', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'director', 'guard_name' => 'web']);
    }
}
