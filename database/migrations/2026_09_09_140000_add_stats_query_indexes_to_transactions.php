<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TransactionStats::baseQuery() filters on a date range plus optionally
 * user_id and/or account_id. The existing (account_id, date) index only
 * helps when an account is picked; Postgres doesn't auto-index the user_id
 * FK, and family scope with no account filter - the default on Family Stats
 * - had no usable index at all. Add the two that match the real access
 * patterns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['user_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'date']);
            $table->dropIndex(['date']);
        });
    }
};
