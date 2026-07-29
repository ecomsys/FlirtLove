<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');

            // === БАЗОВАЯ ИНФА ===
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('birth_date')->nullable();
            $table->enum('dating_goal', ['friends', 'romantic', 'family', 'casual'])->nullable();
            $table->string('city')->nullable();
            
            // Текстовые блоки анкеты
            $table->string('status')->nullable();                 // Статус (вверху анкеты, короткий текст)
            $table->text('bio')->nullable();                      // Свободно о себе (длинный текст)
            $table->text('looking_for')->nullable();              // Кого я хочу найти (информативный текст)
            $table->json('interests')->nullable();                // Интересы (теги)

            // === ВНЕШНОСТЬ (Одиночный выбор -> TINYINT) ===
            $table->unsignedTinyInteger('body_type')->default(0);
            $table->unsignedTinyInteger('eye_color')->default(0);
            $table->unsignedTinyInteger('hair_color')->default(0);
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('weight')->nullable();

            // === ЛИЧНЫЕ ДАННЫЕ (Одиночный выбор -> TINYINT) ===
            $table->unsignedTinyInteger('relationship_status')->default(0);
            $table->unsignedTinyInteger('children_status')->default(0);
            $table->unsignedTinyInteger('pets')->default(0);
            $table->unsignedTinyInteger('housing')->default(0);
            $table->unsignedTinyInteger('has_car')->default(0);
            $table->unsignedTinyInteger('smoking')->default(0);
            $table->unsignedTinyInteger('alcohol')->default(0);

            // === ЛИЧНЫЕ ДАННЫЕ (Множественный выбор -> JSON) ===
            $table->json('body_decorations')->nullable(); 
            $table->json('languages')->nullable();
            $table->json('sports')->nullable();

            // === РАБОТА И ОБРАЗОВАНИЕ ===
            $table->string('education')->nullable();              // Оставляем как было
            $table->string('occupation')->nullable();             // Оставляем как было
            $table->string('institution')->nullable();            // Учебное заведение
            $table->unsignedSmallInteger('institution_year')->nullable(); // Год выпуска
            $table->string('activity')->nullable();               // Сфера деятельности
            $table->string('position')->nullable();               // Должность

            // === ОСТАЛЬНОЕ ===
            $table->string('zodiac_sign')->nullable();

            // === ГЕОЛОКАЦИЯ ===
            $table->geography('location', subtype: 'point')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();

            // === СЧЕТЧИКИ ===
            $table->unsignedInteger('profile_views')->default(0);
            $table->unsignedInteger('likes_count')->default(0);

            $table->timestamps();

            // === ИНДЕКСЫ ===
            $table->index('birth_date'); 
            $table->index('city');
            $table->spatialIndex('location');
            
            // Индексы на самые популярные фильтры
            $table->index('body_type');
            $table->index('smoking');
            $table->index('relationship_status');
            $table->index('education');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};