<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->foreignId('ai_request_id')->nullable()->after('conversation_id')->constrained('ai_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ai_request_id');
        });
    }
};