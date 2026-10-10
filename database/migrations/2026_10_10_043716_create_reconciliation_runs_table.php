<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('layer', 16);
            $table->string('status', 32)->default('running');
            $table->jsonb('scope')->nullable();
            $table->jsonb('summary')->nullable();
            $table->unsignedInteger('items_count')->default(0);
            $table->unsignedInteger('discrepancies_count')->default(0);
            $table->text('error')->nullable();
            $table->uuid('initiated_by')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('initiated_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['layer', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE reconciliation_runs ADD CONSTRAINT reconciliation_runs_layer_check
             CHECK (layer IN ('wallet', 'provider', 'settlement'))"
        );
        DB::statement(
            "ALTER TABLE reconciliation_runs ADD CONSTRAINT reconciliation_runs_status_check
             CHECK (status IN ('running', 'completed', 'completed_with_discrepancies', 'failed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
