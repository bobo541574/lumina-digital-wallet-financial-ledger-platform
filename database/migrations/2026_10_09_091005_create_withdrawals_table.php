<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 32)->unique();
            $table->uuid('user_id');
            $table->uuid('wallet_id');
            $table->string('currency_code', 3);
            $table->bigInteger('amount');
            $table->bigInteger('fee')->default(0);
            $table->string('status', 16)->default('pending');
            $table->uuid('hold_id')->nullable();
            $table->string('provider', 32)->nullable();
            $table->jsonb('destination')->nullable();
            $table->string('provider_reference', 128)->nullable();
            $table->string('provider_transaction_id', 128)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('requested_by')->nullable();
            $table->uuid('processed_by')->nullable();
            $table->timestampTz('initiated_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['wallet_id', 'currency_code'])
                ->references(['id', 'currency_code'])
                ->on('wallets')
                ->restrictOnDelete();
            $table->foreign('hold_id')->references('id')->on('wallet_holds')->nullOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['wallet_id', 'created_at']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX withdrawals_provider_transaction_unique
             ON withdrawals (provider, provider_transaction_id)
             WHERE provider_transaction_id IS NOT NULL'
        );

        DB::statement('ALTER TABLE withdrawals ADD CONSTRAINT withdrawals_amount_positive CHECK (amount > 0)');
        DB::statement('ALTER TABLE withdrawals ADD CONSTRAINT withdrawals_fee_non_negative CHECK (fee >= 0)');
        DB::statement(
            "ALTER TABLE withdrawals ADD CONSTRAINT withdrawals_status_check CHECK (status IN (
                'pending', 'reserved', 'processing', 'reconciling',
                'succeeded', 'failed', 'cancelled'
            ))"
        );

        // Added after withdrawals exists (wallet_holds is created first).
        Schema::table('wallet_holds', function (Blueprint $table) {
            $table->foreign('withdrawal_id')->references('id')->on('withdrawals')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
