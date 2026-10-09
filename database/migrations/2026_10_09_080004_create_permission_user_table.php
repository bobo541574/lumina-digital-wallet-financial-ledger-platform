<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_user', function (Blueprint $table) {
            $table->uuid('permission_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->primary(['permission_id', 'user_id']);

            $table->foreign('permission_id')->references('id')->on('permissions')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_user');
    }
};
