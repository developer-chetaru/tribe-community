<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
 public function up()
{
    if (!Schema::hasColumn('organisations', 'industry_id')) {
        Schema::table('organisations', function (Blueprint $table) {
            $table->foreignId('industry_id')
                  ->nullable()
                  ->constrained('industries')
                  ->onDelete('set null');
        });
    }
}


public function down(): void
{
    Schema::table('organisations', function (Blueprint $table) {
        $table->dropForeign(['industry_id']);
        $table->dropColumn('industry_id');
    });
}

};
