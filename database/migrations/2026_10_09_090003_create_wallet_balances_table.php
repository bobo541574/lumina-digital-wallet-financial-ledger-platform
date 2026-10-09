<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_balances', function (Blueprint $table) {
            $table->uuid('wallet_id')->primary();
            $table->string('currency_code', 3);
            $table->bigInteger('posted_balance')->default(0);
            $table->bigInteger('reserved_balance')->default(0);
            $table->timestamps();

            $table->foreign(['wallet_id', 'currency_code'])
                ->references(['id', 'currency_code'])
                ->on('wallets')
                ->cascadeOnDelete();

            $table->index('currency_code');
        });

        DB::statement(
            'ALTER TABLE wallet_balances ADD CONSTRAINT wallet_balances_posted_non_negative CHECK (posted_balance >= 0)'
        );
        DB::statement(
            'ALTER TABLE wallet_balances ADD CONSTRAINT wallet_balances_reserved_non_negative CHECK (reserved_balance >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_balances');
    }
};
