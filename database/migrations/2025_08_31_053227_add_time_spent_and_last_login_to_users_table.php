<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Add first_login_at column if it doesn't exist
            if (!Schema::hasColumn('users', 'first_login_at')) {
                $table->timestamp('first_login_at')->nullable()->after('last_login_at');
            }
            
            // Add last_login_at column if it doesn't exist
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            
            // Add time_spent_on_app column if it doesn't exist
            if (!Schema::hasColumn('users', 'time_spent_on_app')) {
                $table->integer('time_spent_on_app')->default(0)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'first_login_at')) {
                $table->dropColumn('first_login_at');
            }
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
            if (Schema::hasColumn('users', 'time_spent_on_app')) {
                $table->dropColumn('time_spent_on_app');
            }
        });
    }
};
