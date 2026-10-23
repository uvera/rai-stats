<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grocery_accounts', function (Blueprint $table) {
            $table->timestamp('promotions_synced_at')->nullable()->after('last_synced_at');
        });

        Schema::create('metro_promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grocery_account_id')->constrained()->cascadeOnDelete();
            $table->string('store_number');
            $table->string('shop_variant_id');
            $table->string('name');
            $table->string('brand')->nullable();
            $table->text('image_url')->nullable();
            $table->bigInteger('gross_cents');
            $table->bigInteger('strike_gross_cents')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['grocery_account_id', 'shop_variant_id']);
        });

        Schema::create('metro_promotion_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metro_promotion_id')->constrained()->cascadeOnDelete();
            $table->string('article_name');
            $table->unsignedInteger('times_bought');
            $table->timestamp('last_purchased_at');
            $table->bigInteger('last_unit_price_cents');
            $table->timestamps();

            $table->unique(['metro_promotion_id', 'article_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metro_promotion_matches');
        Schema::dropIfExists('metro_promotions');

        Schema::table('grocery_accounts', function (Blueprint $table) {
            $table->dropColumn('promotions_synced_at');
        });
    }
};
