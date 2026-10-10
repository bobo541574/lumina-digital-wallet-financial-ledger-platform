<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_settlement_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('settlement_id');
            $table->string('type', 16);
            $table->string('provider_transaction_id', 128)->nullable();
            $table->string('reference', 128)->nullable();
            $table->string('currency_code', 3);
            $table->bigInteger('gross_amount')->default(0);
            $table->bigInteger('fee_amount')->default(0);
            $table->bigInteger('net_amount')->default(0);
            $table->boolean('matched')->default(false);
            $table->string('matched_type', 16)->nullable();
            $table->uuid('matched_id')->nullable();
            $table->bigInteger('difference')->nullable();
            $table->timestampTz('occurred_at')->nullable();
            $table->timestamps();

            $table->foreign('settlement_id')->references('id')->on('provider_settlements')->cascadeOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies');

            $table->index(['settlement_id']);
            $table->index(['provider_transaction_id']);
            $table->index(['matched']);
        });

        DB::statement(
            "ALTER TABLE provider_settlement_lines ADD CONSTRAINT provider_settlement_lines_type_check
             CHECK (type IN ('deposit', 'withdrawal', 'fee', 'adjustment'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_settlement_lines');
    }
};
