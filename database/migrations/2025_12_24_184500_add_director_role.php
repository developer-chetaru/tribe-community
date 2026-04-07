<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add director role if it doesn't exist
        Role::firstOrCreate(
            ['name' => 'director', 'guard_name' => 'web']
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove director role if it exists
        $role = Role::where('name', 'director')->where('guard_name', 'web')->first();
        if ($role) {
            $role->delete();
        }
    }
};

