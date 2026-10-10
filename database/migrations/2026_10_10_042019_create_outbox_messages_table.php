<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('aggregate_type', 50);
            $table->uuid('aggregate_id');
            $table->string('event_type', 100);
            $table->jsonb('payload')->nullable();
            $table->string('status', 16)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at');
            $table->timestampTz('processed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index(['aggregate_type', 'aggregate_id']);
        });

        DB::statement(
            "ALTER TABLE outbox_messages ADD CONSTRAINT outbox_messages_status_check
             CHECK (status IN ('pending', 'processing', 'processed', 'failed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
