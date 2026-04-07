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
        Schema::table('reflection_messages', function (Blueprint $table) {
            // Change file column from string to text to support multiple files (JSON array)
            $table->text('file')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reflection_messages', function (Blueprint $table) {
            // Revert back to string (VARCHAR 255) - note: this may truncate existing data
            $table->string('file')->nullable()->change();
        });
    }
};
