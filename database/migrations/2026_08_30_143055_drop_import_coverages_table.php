<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The import flow no longer tracks per-account coverage ranges - imports
     * re-fetch the requested range and rely on (account_id, dedup_key) to
     * skip rows that already exist.
     */
    public function up(): void
    {
        Schema::dropIfExists('import_coverages');
    }

    public function down(): void
    {
        Schema::create('import_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->date('from_date');
            $table->date('to_date');
            $table->timestamps();

            $table->index(['account_id', 'from_date', 'to_date']);
        });
    }
};
