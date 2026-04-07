<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Make sure role exists
        $role = Role::firstOrCreate(['name' => 'super_admin']);

        // Create Super Admin user
        $user = User::create([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'app@chetaru.com',
        'password' => Hash::make('app@123'),
            'image' => null,
            'status' => true,
        ]);

        // Assign Role
        $user->assignRole($role);
    }
}
