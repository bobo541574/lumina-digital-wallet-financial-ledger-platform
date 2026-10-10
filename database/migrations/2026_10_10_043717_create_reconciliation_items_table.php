<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->string('layer', 16);
            $table->string('classification', 24);
            $table->string('subject_type', 64);
            $table->uuid('subject_id')->nullable();
            $table->string('provider', 32)->nullable();
            $table->string('currency_code', 3)->nullable();
            $table->bigInteger('difference')->nullable();
            $table->jsonb('expected')->nullable();
            $table->jsonb('actual')->nullable();
            $table->string('status', 16)->default('open');
            $table->jsonb('resolution')->nullable();
            $table->text('resolution_note')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('run_id')->references('id')->on('reconciliation_runs')->cascadeOnDelete();
            $table->foreign('resolved_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['run_id']);
            $table->index(['status', 'created_at']);
            $table->index(['classification']);
            $table->index(['subject_type', 'subject_id']);
            $table->index(['provider', 'currency_code']);
        });

        DB::statement(
            "ALTER TABLE reconciliation_items ADD CONSTRAINT reconciliation_items_layer_check
             CHECK (layer IN ('wallet', 'provider', 'settlement'))"
        );
        DB::statement(
            "ALTER TABLE reconciliation_items ADD CONSTRAINT reconciliation_items_classification_check
             CHECK (classification IN ('missing', 'duplicate', 'amount_mismatch', 'status_mismatch', 'timing'))"
        );
        DB::statement(
            "ALTER TABLE reconciliation_items ADD CONSTRAINT reconciliation_items_status_check
             CHECK (status IN ('open', 'resolved', 'ignored'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
    }
};
