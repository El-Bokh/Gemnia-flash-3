<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('gumroad_product_id')->nullable()->after('gateway_subscription_id');
            $table->string('gumroad_sale_id')->nullable()->after('gumroad_product_id');
            $table->string('gumroad_variant')->nullable()->after('gumroad_sale_id');
            $table->string('gumroad_license_key')->nullable()->after('gumroad_variant');

            $table->index('gumroad_sale_id');
            $table->index('gumroad_product_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['gumroad_sale_id']);
            $table->dropIndex(['gumroad_product_id']);
            $table->dropColumn([
                'gumroad_product_id',
                'gumroad_sale_id',
                'gumroad_variant',
                'gumroad_license_key',
            ]);
        });
    }
};
