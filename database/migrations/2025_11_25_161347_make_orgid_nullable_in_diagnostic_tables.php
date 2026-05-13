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
        // Make orgId nullable in diagnostic_answers table
        Schema::table('diagnostic_answers', function (Blueprint $table) {
            // Drop the foreign key first
            $table->dropForeign(['orgId']);
            // Make the column nullable
            $table->unsignedBigInteger('orgId')->nullable()->change();
            // Re-add the foreign key with nullable support
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('cascade');
        });

        // Make orgId nullable in diagnostic_individual_user_status table
        Schema::table('diagnostic_individual_user_status', function (Blueprint $table) {
            // Drop the foreign key first
            $table->dropForeign(['orgId']);
            // Make the column nullable
            $table->unsignedBigInteger('orgId')->nullable()->change();
            // Re-add the foreign key with nullable support
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert diagnostic_answers table
        Schema::table('diagnostic_answers', function (Blueprint $table) {
            $table->dropForeign(['orgId']);
            $table->unsignedBigInteger('orgId')->nullable(false)->change();
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('cascade');
        });

        // Revert diagnostic_individual_user_status table
        Schema::table('diagnostic_individual_user_status', function (Blueprint $table) {
            $table->dropForeign(['orgId']);
            $table->unsignedBigInteger('orgId')->nullable(false)->change();
            $table->foreign('orgId')->references('id')->on('organisations')->onDelete('cascade');
        });
    }
};
