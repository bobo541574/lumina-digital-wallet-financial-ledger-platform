<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 32)->unique();
            $table->uuid('wallet_id');
            $table->string('currency_code', 3);
            $table->bigInteger('amount');
            $table->string('status', 16)->default('active');
            $table->uuid('withdrawal_id')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestamps();

            $table->foreign(['wallet_id', 'currency_code'])
                ->references(['id', 'currency_code'])
                ->on('wallets')
                ->restrictOnDelete();

            $table->index(['wallet_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        DB::statement('ALTER TABLE wallet_holds ADD CONSTRAINT wallet_holds_amount_positive CHECK (amount > 0)');
        DB::statement(
            "ALTER TABLE wallet_holds ADD CONSTRAINT wallet_holds_status_check
             CHECK (status IN ('active', 'consumed', 'released', 'expired'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_holds');
    }
};
