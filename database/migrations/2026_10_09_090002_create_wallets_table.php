<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('currency_code', 3);
            $table->string('name', 100)->nullable();
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies');
            $table->unique(['user_id', 'currency_code']);

            // Redundant given the primary key, but required so related financial
            // tables can carry a composite (wallet_id, currency_code) foreign key.
            $table->unique(['id', 'currency_code']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
