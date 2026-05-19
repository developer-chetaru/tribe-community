<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_chat_messages', function (Blueprint $table) {
            $table->json('actions')->nullable()->after('content');
            $table->json('metadata')->nullable()->after('actions');
        });
    }

    public function down(): void
    {
        Schema::table('support_chat_messages', function (Blueprint $table) {
            $table->dropColumn(['actions', 'metadata']);
        });
    }
};
