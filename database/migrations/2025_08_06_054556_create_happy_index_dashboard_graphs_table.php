<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHappyIndexDashboardGraphsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('happy_index_dashboard_graphs', function (Blueprint $table) {
            $table->id()->comment('Primary Key');

            $table->unsignedBigInteger('orgId')->nullable()->index()->comment('Organisation ID (Foreign key)');
            $table->unsignedBigInteger('officeId')->nullable()->index()->comment('Office ID (Foreign key)');
            $table->unsignedBigInteger('departmentId')->nullable()->index()->comment('Department ID (Foreign key)');
            $table->unsignedBigInteger('categoryId')->nullable()->index()->comment('Category ID (Foreign key)');

            $table->date('date')->nullable()->comment('Date of the entry');

            $table->decimal('with_weekend', 5, 2)->nullable()->comment('Happy index including weekends');
            $table->decimal('without_weekend', 5, 2)->nullable()->comment('Happy index excluding weekends');

            $table->string('status')->default('Active')->comment('Status of the entry');

            $table->timestamps(); // created_at & updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('happy_index_dashboard_graphs');
    }
}
