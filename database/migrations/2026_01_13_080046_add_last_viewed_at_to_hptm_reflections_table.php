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
        if (!Schema::hasColumn('hptm_reflections', 'last_viewed_at')) {
            Schema::table('hptm_reflections', function (Blueprint $table) {
                $table->timestamp('last_viewed_at')->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('hptm_reflections', 'last_viewed_at')) {
            Schema::table('hptm_reflections', function (Blueprint $table) {
                $table->dropColumn('last_viewed_at');
            });
        }
    }
};
