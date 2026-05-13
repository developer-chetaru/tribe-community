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
        // Add foreign key for questionId in cot_answers if table exists
        if (Schema::hasTable('cot_answers') && Schema::hasTable('cot_questions')) {
            Schema::table('cot_answers', function (Blueprint $table) {
                $table->foreign('questionId')->references('id')->on('cot_questions')->onDelete('set null');
            });
        }

        // Update cot_role_map_options to link to role descriptions
        if (Schema::hasTable('cot_role_map_options') && Schema::hasTable('cot_team_role_descriptions')) {
            Schema::table('cot_role_map_options', function (Blueprint $table) {
                $table->unsignedBigInteger('role_description_id')->nullable()->after('categoryId');
                $table->foreign('role_description_id')->references('id')->on('cot_team_role_descriptions')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('cot_answers')) {
            Schema::table('cot_answers', function (Blueprint $table) {
                $table->dropForeign(['questionId']);
            });
        }

        if (Schema::hasTable('cot_role_map_options')) {
            Schema::table('cot_role_map_options', function (Blueprint $table) {
                $table->dropForeign(['role_description_id']);
                $table->dropColumn('role_description_id');
            });
        }
    }
};
