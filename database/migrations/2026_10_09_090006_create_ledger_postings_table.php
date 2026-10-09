<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_postings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->uuid('ledger_account_id');
            $table->string('currency_code', 3);
            $table->uuid('wallet_id')->nullable();
            $table->string('direction', 6);
            $table->bigInteger('amount');
            $table->timestamps();

            // Currency consistency is enforced by composite foreign keys:
            // a posting must match both the journal's and the account's currency.
            $table->foreign(['journal_entry_id', 'currency_code'])
                ->references(['id', 'currency_code'])
                ->on('journal_entries')
                ->restrictOnDelete();

            $table->foreign(['ledger_account_id', 'currency_code'])
                ->references(['id', 'currency_code'])
                ->on('ledger_accounts')
                ->restrictOnDelete();

            $table->foreign(['wallet_id', 'currency_code'])
                ->references(['id', 'currency_code'])
                ->on('wallets')
                ->nullOnDelete();

            $table->index(['ledger_account_id', 'created_at']);
            $table->index(['journal_entry_id']);
        });

        DB::statement(
            'ALTER TABLE ledger_postings ADD CONSTRAINT ledger_postings_amount_positive CHECK (amount > 0)'
        );
        DB::statement(
            "ALTER TABLE ledger_postings ADD CONSTRAINT ledger_postings_direction_check CHECK (direction IN ('debit', 'credit'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_postings');
    }
};
