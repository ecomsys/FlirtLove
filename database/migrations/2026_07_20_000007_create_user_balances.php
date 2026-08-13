<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // === ВАЛЮТА ===
            $table->unsignedInteger('credits')->default(0);

            // === ЛИМИТЫ (Суперлайки) ===
            $table->unsignedInteger('superlikes_remaining')->default(5);
            $table->timestamp('superlikes_reset_at')->nullable();
            
            // На будущее (Бусты профиля)
            $table->unsignedInteger('boosts_remaining')->default(0);
            $table->timestamp('boosts_reset_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_balances');
    }
};