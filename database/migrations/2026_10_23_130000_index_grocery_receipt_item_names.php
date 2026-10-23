<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Article prices groups every receipt line by its exact name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grocery_receipt_items', function (Blueprint $table) {
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::table('grocery_receipt_items', function (Blueprint $table) {
            $table->dropIndex(['name']);
        });
    }
};
