<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 64);
            $table->string('name', 128);
            $table->string('type', 16);
            $table->string('currency_code', 3);
            $table->string('status', 16)->default('active');
            $table->uuid('wallet_id')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->foreign('wallet_id')->references('id')->on('wallets')->nullOnDelete();

            $table->unique(['id', 'currency_code']);
            $table->unique('wallet_id');
            $table->index(['type', 'currency_code']);
        });

        // System accounts (wallet_id IS NULL) are unique by (code, currency),
        // while many customer wallet accounts may share the same code.
        DB::statement(
            'CREATE UNIQUE INDEX ledger_accounts_system_code_unique
             ON ledger_accounts (code, currency_code)
             WHERE wallet_id IS NULL'
        );

        DB::statement(
            "ALTER TABLE ledger_accounts ADD CONSTRAINT ledger_accounts_type_check CHECK (type IN ('asset', 'liability', 'revenue', 'expense', 'equity'))"
        );
        DB::statement(
            "ALTER TABLE ledger_accounts ADD CONSTRAINT ledger_accounts_status_check CHECK (status IN ('active', 'closed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_accounts');
    }
};
