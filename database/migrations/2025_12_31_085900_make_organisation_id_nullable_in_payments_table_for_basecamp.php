<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Add user_id for basecamp users
            if (!Schema::hasColumn('payments', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('organisation_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            }
            // Make organisation_id nullable for basecamp users
            $table->unsignedBigInteger('organisation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Drop user_id if exists
            if (Schema::hasColumn('payments', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
            // Revert organisation_id to not nullable if needed
            // Note: This might fail if there are NULL values in the database
            $table->unsignedBigInteger('organisation_id')->nullable(false)->change();
        });
    }
};

