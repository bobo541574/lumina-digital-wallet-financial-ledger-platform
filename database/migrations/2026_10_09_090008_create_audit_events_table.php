<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->nullableUuidMorphs('actor');
            $table->string('action', 128);
            $table->nullableUuidMorphs('subject');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->string('correlation_id', 64)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            $table->foreign('actor_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['action', 'occurred_at']);
            $table->index('correlation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
