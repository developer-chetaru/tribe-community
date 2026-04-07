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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('invoice_id');
            $table->unsignedBigInteger('organisation_id');
            $table->unsignedBigInteger('paid_by_user_id')->nullable(); // Director who made the payment
            $table->string('payment_method')->default('bank_transfer'); // bank_transfer, credit_card, paypal, etc.
            $table->decimal('amount', 10, 2);
            $table->string('transaction_id')->nullable();
            $table->string('status')->default('pending'); // pending, completed, failed, refunded
            $table->date('payment_date')->nullable();
            $table->text('payment_notes')->nullable();
            $table->text('payment_proof')->nullable(); // File path or URL for payment proof
            $table->timestamps();

            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('organisation_id')->references('id')->on('organisations')->onDelete('cascade');
            $table->foreign('paid_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->index('invoice_id');
            $table->index('organisation_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
