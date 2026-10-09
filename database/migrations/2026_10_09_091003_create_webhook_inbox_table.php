<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_inbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 32);
            $table->string('event_id', 128);
            $table->string('event_type', 64)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->string('status', 16)->default('received');
            $table->jsonb('payload')->nullable();
            $table->uuid('deposit_id')->nullable();
            $table->text('note')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('deposit_id')->references('id')->on('deposits')->nullOnDelete();
            $table->unique(['provider', 'event_id']);
            $table->index(['status', 'created_at']);
        });

        DB::statement(
            "ALTER TABLE webhook_inbox ADD CONSTRAINT webhook_inbox_status_check
             CHECK (status IN ('received', 'processed', 'duplicate', 'quarantined', 'rejected'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_inbox');
    }
};
