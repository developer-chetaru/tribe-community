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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organisation_id');
            $table->integer('user_count')->default(0); // Number of users in subscription
            $table->decimal('price_per_user', 10, 2)->default(0.00); // Price per user per month
            $table->decimal('total_amount', 10, 2)->default(0.00); // Total monthly amount
            $table->string('status')->default('active'); // active, suspended, cancelled
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_billing_date');
            $table->string('billing_cycle')->default('monthly'); // monthly, yearly
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');
            $table->index('organisation_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
