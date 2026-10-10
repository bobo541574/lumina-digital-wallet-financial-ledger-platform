<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_settlements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 32);
            $table->string('settlement_reference', 128);
            $table->string('currency_code', 3);
            $table->bigInteger('gross_amount')->default(0);
            $table->bigInteger('fee_amount')->default(0);
            $table->bigInteger('net_amount')->default(0);
            $table->string('status', 16)->default('received');
            $table->date('statement_date');
            $table->jsonb('raw')->nullable();
            $table->timestampTz('reconciled_at')->nullable();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies');

            $table->unique(['provider', 'settlement_reference']);
            $table->index(['provider', 'statement_date']);
            $table->index(['status']);
        });

        DB::statement(
            "ALTER TABLE provider_settlements ADD CONSTRAINT provider_settlements_status_check
             CHECK (status IN ('received', 'matched', 'discrepancy'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_settlements');
    }
};
