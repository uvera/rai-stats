<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('date');
            $table->integer('amount_cents');
            $table->string('currency_code');
            $table->string('place');
            $table->string('reference')->nullable();
            $table->text('description')->nullable();
            $table->string('type');
            $table->string('bank_transaction_id')->nullable();
            $table->string('dedup_key');
            $table->timestamps();

            $table->unique(['account_id', 'dedup_key']);
            $table->index(['account_id', 'date']);
            $table->index('type');
            $table->index('place');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
