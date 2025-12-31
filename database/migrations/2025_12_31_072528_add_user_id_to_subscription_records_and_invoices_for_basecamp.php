<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Make organisation_id nullable in subscription_records for basecamp users
        Schema::table('subscription_records', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('organisation_id')->constrained()->onDelete('cascade');
        });
        
        // Add user_id to invoices for basecamp users
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('organisation_id')->constrained()->onDelete('cascade');
            $table->string('tier')->nullable()->after('user_id'); // Add tier field if not exists
        });
        
        // Make organisation_id nullable in subscription_records
        Schema::table('subscription_records', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->change();
        });
        
        // Make organisation_id nullable in invoices
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('organisation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'tier']);
            $table->foreignId('organisation_id')->nullable(false)->change();
        });
        
        Schema::table('subscription_records', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->foreignId('organisation_id')->nullable(false)->change();
        });
    }
};
