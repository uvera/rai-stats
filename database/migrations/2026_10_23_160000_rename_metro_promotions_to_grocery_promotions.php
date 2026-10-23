<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metro_promotions', function (Blueprint $table) {
            $table->string('source')->default('ranked');
        });

        DB::statement('ALTER TABLE metro_promotions ALTER COLUMN source DROP DEFAULT');

        Schema::table('metro_promotions', function (Blueprint $table) {
            $table->dropUnique(['grocery_account_id', 'shop_variant_id']);
            $table->unique(
                ['grocery_account_id', 'shop_variant_id', 'source'],
                'grocery_promotions_account_variant_source_unique',
            );
        });

        Schema::rename('metro_promotion_matches', 'grocery_promotion_matches');
        Schema::rename('metro_promotions', 'grocery_promotions');

        Schema::table('grocery_promotion_matches', function (Blueprint $table) {
            $table->renameColumn('metro_promotion_id', 'grocery_promotion_id');
        });
    }

    public function down(): void
    {
        Schema::table('grocery_promotion_matches', function (Blueprint $table) {
            $table->renameColumn('grocery_promotion_id', 'metro_promotion_id');
        });

        Schema::rename('grocery_promotions', 'metro_promotions');
        Schema::rename('grocery_promotion_matches', 'metro_promotion_matches');

        Schema::table('metro_promotions', function (Blueprint $table) {
            $table->dropUnique('grocery_promotions_account_variant_source_unique');
            $table->dropColumn('source');
            $table->unique(['grocery_account_id', 'shop_variant_id']);
        });
    }
};
