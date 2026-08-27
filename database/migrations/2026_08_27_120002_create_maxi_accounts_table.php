<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maxi_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('email')->unique();
            // Encrypted at rest (model cast). Only the ~1-year Moj Maxi JWT is
            // stored - never the password (see docs / plan).
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('device_uuid');
            // Which rai-stats user's bank transactions receipts are matched
            // against. Nullable so an account can be added before deciding.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maxi_accounts');
    }
};
