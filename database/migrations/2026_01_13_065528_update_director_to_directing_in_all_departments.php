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
        // Update "Director" to "Directing" in all_departments table
        \DB::table('all_departments')
            ->where('name', 'Director')
            ->update(['name' => 'Directing']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert "Directing" back to "Director"
        \DB::table('all_departments')
            ->where('name', 'Directing')
            ->update(['name' => 'Director']);
    }
};
