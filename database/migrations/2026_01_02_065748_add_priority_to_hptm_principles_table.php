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
        if (Schema::hasTable('hptm_principles')) {
            Schema::table('hptm_principles', function (Blueprint $table) {
                // Add priority column if it doesn't exist
                if (!Schema::hasColumn('hptm_principles', 'priority')) {
                    $table->integer('priority')->default(0)->after('description');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('hptm_principles')) {
            Schema::table('hptm_principles', function (Blueprint $table) {
                if (Schema::hasColumn('hptm_principles', 'priority')) {
                    $table->dropColumn('priority');
                }
            });
        }
    }
};
