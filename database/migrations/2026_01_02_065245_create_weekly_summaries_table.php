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
        if (!Schema::hasTable('weekly_summaries')) {
            Schema::create('weekly_summaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->integer('year');
                $table->integer('month');
                $table->integer('week_number');
                $table->string('week_label')->nullable();
                $table->text('summary')->nullable();
                $table->timestamps();

                // Indexes for better query performance
                $table->index(['user_id', 'year', 'month']);
                $table->index(['user_id', 'year', 'month', 'week_number']);
            });
        } else {
            // Table exists, verify columns and indexes
            Schema::table('weekly_summaries', function (Blueprint $table) {
                // Add missing columns if they don't exist
                if (!Schema::hasColumn('weekly_summaries', 'week_label')) {
                    $table->string('week_label')->nullable()->after('week_number');
                }
                
                // Add missing indexes if they don't exist
                $indexes = DB::select("SHOW INDEXES FROM weekly_summaries WHERE Key_name LIKE 'weekly_summaries_user_id%'");
                $hasCompositeIndex = false;
                foreach ($indexes as $index) {
                    if (strpos($index->Key_name, 'user_id') !== false && strpos($index->Key_name, 'year') !== false) {
                        $hasCompositeIndex = true;
                        break;
                    }
                }
                
                if (!$hasCompositeIndex) {
                    try {
                        $table->index(['user_id', 'year', 'month'], 'weekly_summaries_user_year_month_index');
                    } catch (\Exception $e) {
                        // Index might already exist with different name
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
        Schema::dropIfExists('weekly_summaries');
    }
};
