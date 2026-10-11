<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('event_id')->nullable();
            $table->string('type', 64);
            $table->string('title', 160);
            $table->text('body')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            // Event handling: at-least-once outbox delivery must not surface the
            // same event twice, so an event can produce at most one row per user.
            $table->unique(['user_id', 'event_id']);

            // Drives the notification centre list and unread badge.
            $table->index(['user_id', 'read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
    }
};
