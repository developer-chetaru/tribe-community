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
        Schema::create('dot_value_list', function (Blueprint $table) {
            $table->id(); // id int auto increment
            $table->string('name', 225); // varchar(225)
            $table->text('value_url')->nullable(); // text
            $table->mediumText('value_desc')->nullable(); // mediumtext
            $table->enum('status', ['Active', 'Inactive']); // enum
            $table->timestamp('created_at')->useCurrent()->nullable();
            $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dot_value_list');
    }
};
