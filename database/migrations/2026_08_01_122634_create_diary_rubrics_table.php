<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('diary_rubrics', function (Blueprint $table) {
            $table->id();

            //  Кто создал рубрику. Если null — рубрика системная (от админа)
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            
            // Название рубрики ("Мысли", "Стихи", "Путешествия")
            $table->string('name');
            
            // ЧПУ-слаг для URL (mysite.com/diary/thoughts)
            $table->string('slug')->unique();
            
            // Описание (инфо для админки, что входит в рубрику)
            $table->text('description')->nullable();
            
            // Управление показом
            $table->boolean('is_active')->default(true);
            
            // Сортировка в меню рубрик
            $table->unsignedSmallInteger('sort_order')->default(0);
            
            $table->timestamps();

            // Защита: юзер не может создать две рубрики с одинаковым slug у себя
            $table->unique(['user_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('diary_rubrics');
    }
};