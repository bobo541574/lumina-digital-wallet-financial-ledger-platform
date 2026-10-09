<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference', 32)->unique();
            $table->uuid('user_id');
            $table->uuid('wallet_id');
            $table->string('currency_code', 3);
            $table->bigInteger('amount');
            $table->string('channel', 16);
            $table->string('status', 24)->default('pending');
            $table->string('provider', 32)->nullable();
            $table->string('provider_reference', 128)->nullable();
            $table->string('provider_transaction_id', 128)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('approved_by')->nullable();
            $table->uuid('confirmed_by')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('settled_at')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign(['wallet_id', 'currency_code'])
                ->references(['id', 'currency_code'])
                ->on('wallets')
                ->restrictOnDelete();
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['user_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index(['wallet_id', 'created_at']);
        });

        // A provider transaction id is unique per provider (partial: manual
        // deposits have none).
        DB::statement(
            'CREATE UNIQUE INDEX deposits_provider_transaction_unique
             ON deposits (provider, provider_transaction_id)
             WHERE provider_transaction_id IS NOT NULL'
        );

        DB::statement('ALTER TABLE deposits ADD CONSTRAINT deposits_amount_positive CHECK (amount > 0)');
        DB::statement("ALTER TABLE deposits ADD CONSTRAINT deposits_channel_check CHECK (channel IN ('manual', 'provider'))");
        DB::statement(
            "ALTER TABLE deposits ADD CONSTRAINT deposits_status_check CHECK (status IN (
                'pending', 'awaiting_approval', 'approved', 'awaiting_payment',
                'processing', 'reconciling', 'succeeded', 'failed', 'cancelled'
            ))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
