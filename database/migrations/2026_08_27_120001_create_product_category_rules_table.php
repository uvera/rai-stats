<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_category_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_category_id')->constrained()->cascadeOnDelete();
            $table->string('pattern');
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();

            $table->index('product_category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_category_rules');
    }
};
