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
        Schema::table('personality_type_questions', function (Blueprint $table) {
            $table->string('category')->nullable()->after('question'); // Int, Ext, Innov, Lgc, Ppl, Tsk, Stru, Flex
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personality_type_questions', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
