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
            // Add working days fields for basecamp users
            $table->boolean('working_monday')->default(true)->after('HI_include_saturday');
            $table->boolean('working_tuesday')->default(true)->after('working_monday');
            $table->boolean('working_wednesday')->default(true)->after('working_tuesday');
            $table->boolean('working_thursday')->default(true)->after('working_wednesday');
            $table->boolean('working_friday')->default(true)->after('working_thursday');
            // Saturday and Sunday already exist as HI_include_saturday and HI_include_sunday
            // Default: Monday-Friday = true, Saturday-Sunday = false (already default)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'working_monday',
                'working_tuesday',
                'working_wednesday',
                'working_thursday',
                'working_friday',
            ]);
        });
    }
};
