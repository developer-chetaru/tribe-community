<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, update existing boolean status values to new ENUM values
        DB::statement("
            UPDATE users 
            SET status = CASE 
                WHEN status = 1 OR status = '1' OR status = 'true' THEN 'active_verified'
                WHEN status = 0 OR status = '0' OR status = 'false' THEN 'pending_payment'
                ELSE 'inactive'
            END
            WHERE email_verified_at IS NOT NULL
        ");
        
        DB::statement("
            UPDATE users 
            SET status = 'active_unverified'
            WHERE email_verified_at IS NULL AND (status = 1 OR status = '1' OR status = 'true')
        ");
        
        DB::statement("
            UPDATE users 
            SET status = 'pending_payment'
            WHERE email_verified_at IS NULL AND (status = 0 OR status = '0' OR status = 'false' OR status IS NULL)
        ");
        
        // Now change column type to ENUM
        Schema::table('users', function (Blueprint $table) {
            $table->enum('status', [
                'pending_payment',
                'active_unverified', 
                'active_verified',
                'suspended',
                'cancelled',
                'inactive'
            ])->default('pending_payment')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert ENUM back to boolean
        DB::statement("
            UPDATE users 
            SET status = CASE 
                WHEN status IN ('active_verified', 'active_unverified') THEN 1
                ELSE 0
            END
        ");
        
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('status')->default(false)->change();
        });
    }
};
