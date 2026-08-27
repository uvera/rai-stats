<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maxi_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maxi_receipt_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_no');
            $table->string('name');
            $table->decimal('quantity', 10, 3)->default(1);
            $table->integer('unit_price_cents');
            $table->integer('total_cents');
            $table->string('vat_label')->nullable();
            $table->decimal('vat_rate', 5, 2)->nullable();
            $table->foreignId('product_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_source')->nullable(); // App\Enums\CategorySource
            $table->timestamps();

            $table->index('product_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maxi_receipt_items');
    }
};
