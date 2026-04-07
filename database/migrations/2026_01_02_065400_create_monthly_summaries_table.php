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
        // Check if table already exists before creating
        if (!Schema::hasTable('monthly_summaries')) {
            Schema::create('monthly_summaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->integer('year');
                $table->integer('month');
                $table->string('month_label')->nullable(); // e.g., "January 2026"
                $table->text('summary')->nullable();
                $table->timestamps();

                // Indexes for better query performance
                $table->index(['user_id', 'year', 'month']);
                $table->unique(['user_id', 'year', 'month']); // One summary per user per month
            });
        } else {
            // Table exists, verify columns and indexes
            Schema::table('monthly_summaries', function (Blueprint $table) {
                // Add missing columns if they don't exist
                if (!Schema::hasColumn('monthly_summaries', 'month_label')) {
                    $table->string('month_label')->nullable()->after('month');
                }
                
                // Add missing unique constraint if it doesn't exist
                $uniqueConstraints = DB::select("
                    SELECT CONSTRAINT_NAME 
                    FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'monthly_summaries' 
                    AND CONSTRAINT_TYPE = 'UNIQUE'
                    AND CONSTRAINT_NAME LIKE '%user_id%year%month%'
                ");
                
                if (empty($uniqueConstraints)) {
                    try {
                        $table->unique(['user_id', 'year', 'month'], 'monthly_summaries_user_year_month_unique');
                    } catch (\Exception $e) {
                        // Unique constraint might already exist
                    }
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_summaries');
    }
};
