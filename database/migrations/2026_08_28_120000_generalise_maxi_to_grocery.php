<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalises the single-provider "Moj Maxi" schema into a provider-agnostic
 * "grocery" one so a second receipt provider (Metro) can share it.
 *
 * Additive + renames only - every existing row is backfilled with
 * provider = 'maxi', so no data is lost and no migrate:fresh is needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('maxi_accounts', 'grocery_accounts');
        Schema::rename('maxi_receipts', 'grocery_receipts');
        Schema::rename('maxi_receipt_items', 'grocery_receipt_items');

        Schema::table('grocery_receipts', function (Blueprint $table) {
            $table->renameColumn('maxi_account_id', 'grocery_account_id');
            $table->renameColumn('invoice_hash', 'external_ref');
        });

        Schema::table('grocery_receipt_items', function (Blueprint $table) {
            $table->renameColumn('maxi_receipt_id', 'grocery_receipt_id');
        });

        Schema::table('grocery_accounts', function (Blueprint $table) {
            $table->string('provider')->default('maxi')->after('id');
            $table->text('refresh_token')->nullable()->after('access_token');
            // Encrypted at rest (model cast). Deliberately stored for these
            // low-value loyalty accounts so a sync can re-authenticate
            // unattended - unlike the bank-import flow.
            $table->text('password')->nullable()->after('refresh_token');
            // Provider-specific customer id (Metro cardholderId, e.g.
            // "RS_22_760829_1").
            $table->string('external_id')->nullable()->after('user_id');

            $table->index('provider');
        });

        Schema::table('grocery_receipts', function (Blueprint $table) {
            $table->string('provider')->default('maxi')->after('grocery_account_id');
            // Metro reports a VAT-exclusive net total alongside the gross one.
            $table->integer('net_total_cents')->nullable()->after('total_cents');

            $table->index('provider');
        });

        Schema::table('grocery_receipt_items', function (Blueprint $table) {
            $table->integer('net_unit_price_cents')->nullable()->after('unit_price_cents');
            $table->integer('net_total_cents')->nullable()->after('total_cents');
        });

        DB::statement(<<<'SQL'
            UPDATE grocery_receipts
            SET provider = grocery_accounts.provider
            FROM grocery_accounts
            WHERE grocery_receipts.grocery_account_id = grocery_accounts.id
        SQL);
    }

    public function down(): void
    {
        Schema::table('grocery_accounts', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropColumn(['provider', 'refresh_token', 'password', 'external_id']);
        });

        Schema::table('grocery_receipts', function (Blueprint $table) {
            $table->dropIndex(['provider']);
            $table->dropColumn(['provider', 'net_total_cents']);
            $table->renameColumn('grocery_account_id', 'maxi_account_id');
            $table->renameColumn('external_ref', 'invoice_hash');
        });

        Schema::table('grocery_receipt_items', function (Blueprint $table) {
            $table->dropColumn(['net_unit_price_cents', 'net_total_cents']);
            $table->renameColumn('grocery_receipt_id', 'maxi_receipt_id');
        });

        Schema::rename('grocery_receipt_items', 'maxi_receipt_items');
        Schema::rename('grocery_receipts', 'maxi_receipts');
        Schema::rename('grocery_accounts', 'maxi_accounts');
    }
};
