<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('limit_type');
            $table->unsignedInteger('max_requests');
            $table->unsignedInteger('used_requests')->default(0);
            $table->enum('period', ['minute', 'hour', 'day', 'week', 'month'])->default('day');
            $table->timestamp('period_started_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'limit_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_limits');
    }
};
