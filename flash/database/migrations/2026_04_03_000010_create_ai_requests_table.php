<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->foreignId('visual_style_id')->nullable()->constrained('visual_styles')->nullOnDelete();
            $table->enum('type', ['text_to_image', 'image_to_image', 'inpainting', 'upscale', 'chat', 'styled_chat', 'multimodal', 'other'])->default('chat');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled', 'timeout'])->default('pending');
            $table->text('user_prompt');
            $table->text('processed_prompt')->nullable();
            $table->text('negative_prompt')->nullable();
            $table->text('hidden_prompt')->nullable();
            $table->string('model_used')->nullable();
            $table->string('engine_provider')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('steps')->nullable();
            $table->decimal('cfg_scale', 5, 2)->nullable();
            $table->string('sampler')->nullable();
            $table->bigInteger('seed')->nullable();
            $table->unsignedInteger('num_images')->default(1);
            $table->unsignedInteger('credits_consumed')->default(0);
            $table->string('input_image_path')->nullable();
            $table->string('mask_image_path')->nullable();
            $table->decimal('denoising_strength', 3, 2)->nullable();
            $table->text('error_message')->nullable();
            $table->string('error_code')->nullable();
            $table->unsignedInteger('retry_count')->default(0);
            $table->unsignedInteger('processing_time_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_requests');
    }
};
