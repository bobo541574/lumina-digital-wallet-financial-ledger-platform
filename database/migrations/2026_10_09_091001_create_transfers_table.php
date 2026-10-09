<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 32)->unique();
            $table->uuid('sender_wallet_id');
            $table->uuid('recipient_wallet_id');
            $table->string('currency_code', 3);
            $table->bigInteger('amount');
            $table->bigInteger('fee')->default(0);
            $table->string('status', 16)->default('pending');
            $table->string('failure_code', 64)->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('initiated_by')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            // Composite FKs guarantee both wallets share the transfer currency.
            $table->foreign(['sender_wallet_id', 'currency_code'])
                ->references(['id', 'currency_code'])
                ->on('wallets')
                ->restrictOnDelete();

            $table->foreign(['recipient_wallet_id', 'currency_code'])
                ->references(['id', 'currency_code'])
                ->on('wallets')
                ->restrictOnDelete();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('initiated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['sender_wallet_id', 'created_at']);
            $table->index(['recipient_wallet_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        DB::statement('ALTER TABLE transfers ADD CONSTRAINT transfers_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE transfers ADD CONSTRAINT transfers_fee_non_negative CHECK (fee >= 0)');
        DB::statement('ALTER TABLE transfers ADD CONSTRAINT transfers_distinct_wallets CHECK (sender_wallet_id <> recipient_wallet_id)');
        DB::statement("ALTER TABLE transfers ADD CONSTRAINT transfers_status_check CHECK (status IN ('pending', 'succeeded', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
