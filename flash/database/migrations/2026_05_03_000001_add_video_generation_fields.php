<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('ai_requests', 'output_video_path')) {
            Schema::table('ai_requests', function (Blueprint $table) {
                $table->string('output_video_path', 2048)->nullable()->after('output_image_path');
            });
        }

        if (! Schema::hasColumn('conversation_messages', 'video_url')) {
            Schema::table('conversation_messages', function (Blueprint $table) {
                $table->string('video_url', 2048)->nullable()->after('image_url');
            });
        }

        if (! Schema::hasColumn('conversation_messages', 'metadata')) {
            Schema::table('conversation_messages', function (Blueprint $table) {
                $table->json('metadata')->nullable()->after('product_images');
            });
        }

        $this->syncAiRequestTypeEnum([
            'text_to_image', 'image_to_image', 'inpainting', 'upscale',
            'chat', 'styled_chat', 'multimodal', 'regenerate', 'product',
            'text_to_video', 'image_to_video', 'other',
        ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('conversation_messages', 'metadata')) {
            Schema::table('conversation_messages', function (Blueprint $table) {
                $table->dropColumn('metadata');
            });
        }

        if (Schema::hasColumn('conversation_messages', 'video_url')) {
            Schema::table('conversation_messages', function (Blueprint $table) {
                $table->dropColumn('video_url');
            });
        }

        if (Schema::hasColumn('ai_requests', 'output_video_path')) {
            Schema::table('ai_requests', function (Blueprint $table) {
                $table->dropColumn('output_video_path');
            });
        }
    }

    private function syncAiRequestTypeEnum(array $values): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $quotedValues = collect($values)
            ->map(fn (string $value) => "'" . str_replace("'", "''", $value) . "'")
            ->implode(',');

        DB::statement("ALTER TABLE ai_requests MODIFY type ENUM({$quotedValues}) NOT NULL DEFAULT 'chat'");
    }
};