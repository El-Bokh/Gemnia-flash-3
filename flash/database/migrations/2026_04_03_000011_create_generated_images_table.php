<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('generated_images', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ai_request_id')->constrained('ai_requests')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('disk')->default('public');
            $table->string('mime_type')->default('image/png');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->boolean('is_public')->default(false);
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_nsfw')->default(false);
            $table->unsignedBigInteger('download_count')->default(0);
            $table->unsignedBigInteger('view_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
            $table->index('ai_request_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_images');
    }
};
