<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Основной путь (для совместимости)
            $table->string('path')->nullable();
            
            // Все версии
            $table->string('path_original')->nullable();
            $table->string('path_large')->nullable();
            $table->string('path_medium')->nullable();
            $table->string('path_thumb')->nullable();
            
            // Статусы
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_intimate')->default(false);
            
            // Порядок сортировки
            $table->integer('position')->default(0);
            
            $table->timestamps();
            
            // Индексы для быстрых запросов
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'is_primary']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('photos');
    }
};