<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('currency_code', 3);
            $table->string('entry_type', 32);
            $table->string('status', 16)->default('posted');
            $table->string('source_type', 64)->nullable();
            $table->string('source_id', 64)->nullable();
            $table->string('description', 255)->nullable();
            $table->uuid('reverses_journal_entry_id')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampTz('posted_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->unique(['id', 'currency_code']);
            $table->unique(['source_type', 'source_id']);
            $table->index(['currency_code', 'occurred_at']);
            $table->index(['reverses_journal_entry_id']);
        });

        // Added after create() so the UUID primary key exists first.
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->foreign('reverses_journal_entry_id')
                ->references('id')
                ->on('journal_entries')
                ->nullOnDelete();
        });

        DB::statement(
            "ALTER TABLE journal_entries ADD CONSTRAINT journal_entries_status_check CHECK (status IN ('posted', 'reversed'))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
