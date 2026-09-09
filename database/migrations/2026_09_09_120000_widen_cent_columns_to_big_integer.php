<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A signed 32-bit integer tops out at 21,474,836.47 in major units
 * (~21.5M RSD / ~€180k). A property sale, a loan disbursement or an
 * FX-account transfer clears that easily, and Postgres aborts the whole
 * insertOrIgnore batch with "integer out of range" mid-import. Widen every
 * *_cents column that holds a real monetary amount to bigint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->bigInteger('amount_cents')->change();
        });

        Schema::table('grocery_receipts', function (Blueprint $table) {
            $table->bigInteger('total_cents')->change();
            $table->bigInteger('net_total_cents')->nullable()->change();
        });

        Schema::table('grocery_receipt_items', function (Blueprint $table) {
            $table->bigInteger('unit_price_cents')->change();
            $table->bigInteger('total_cents')->change();
            $table->bigInteger('net_unit_price_cents')->nullable()->change();
            $table->bigInteger('net_total_cents')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->integer('amount_cents')->change();
        });

        Schema::table('grocery_receipts', function (Blueprint $table) {
            $table->integer('total_cents')->change();
            $table->integer('net_total_cents')->nullable()->change();
        });

        Schema::table('grocery_receipt_items', function (Blueprint $table) {
            $table->integer('unit_price_cents')->change();
            $table->integer('total_cents')->change();
            $table->integer('net_unit_price_cents')->nullable()->change();
            $table->integer('net_total_cents')->nullable()->change();
        });
    }
};
