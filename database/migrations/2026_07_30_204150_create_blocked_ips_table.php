<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_ips', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address')->unique(); // Сам IP
            $table->string('reason')->nullable(); // Причина бана (например, "Ботоварка")
            $table->foreignId('blocked_by')->nullable()->constrained('users'); // Кто забанил (админ)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blocked_ips');
    }
};