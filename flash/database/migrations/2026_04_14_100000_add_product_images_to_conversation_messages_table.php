<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->json('product_images')->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_messages', function (Blueprint $table) {
            $table->dropColumn('product_images');
        });
    }
};
