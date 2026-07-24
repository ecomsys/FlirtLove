<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('albums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Если юзер удален, альбомы тоже удаляются
            $table->string('name')->default('Общие'); // Название альбома
            $table->text('description')->nullable();  // Описание (опционально)
            $table->boolean('is_default')->default(false); // Флаг "альбом по умолчанию"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('albums');
    }
};