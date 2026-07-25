<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('swipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('target_user_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['like', 'dislike', 'superlike'])->default('like');
            $table->timestamps();

            // Уникальность пары (user_id, target_user_id) – чтобы не дублировать
            $table->unique(['user_id', 'target_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('swipes');
    }
};