<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maxi_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maxi_account_id')->constrained()->cascadeOnDelete();
            // Moj Maxi's stable per-invoice id (InvoiceNumberHash) - the dedup key.
            $table->string('invoice_hash');
            $table->string('pfr_number')->nullable();
            // The suf.purs.gov.rs "vl" verification token, kept for a future
            // PURS-API path; not used yet.
            $table->text('purs_vl')->nullable();
            $table->string('store_name');
            $table->string('store_address')->nullable();
            $table->string('store_format')->nullable();
            $table->timestamp('purchased_at');
            $table->integer('total_cents');
            $table->string('currency_code')->default('RSD');
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('match_source')->nullable(); // App\Enums\ReceiptMatchSource
            $table->text('raw_text')->nullable();
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['maxi_account_id', 'invoice_hash']);
            $table->index('purchased_at');
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maxi_receipts');
    }
};
