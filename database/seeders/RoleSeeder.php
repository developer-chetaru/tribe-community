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
        // Create roles for both 'web' and 'api' guards
        $roles = [
            'super_admin',
            'organisation_super_admin',
            'organisation_admin',
            'organisation_user',
            'basecamp',
            'director',
        ];

        foreach ($roles as $roleName) {
            // Create for web guard
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            // Create for api guard
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);
        }
    }
}
