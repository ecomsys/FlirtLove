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
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // === БАЗОВАЯ ИНФА ===
            $table->enum('gender', ['male', 'female'])->nullable()->index();
            $table->date('birth_date')->nullable()->index();
            $table->enum('dating_goal', ['friends', 'romantic', 'family', 'casual', 'travel'])->nullable()->index();
            $table->string('city')->nullable()->index();
            $table->string('country')->nullable();
            
            // === ТЕКСТОВЫЕ БЛОКИ ===
            $table->string('headline')->nullable(); // Короткий статус
            $table->text('bio')->nullable(); // Свободно о себе
            $table->text('looking_for')->nullable(); // Кого я хочу найти
            $table->json('interests')->nullable(); // Теги

            // === АВТОПОРТРЕТ (Любимое, Отношение к жизни и т.д.) ===
            // Вся простыня текстовых полей, которые не участвуют в поиске.
            // Структура: {"favorite_music": "...", "favorite_movies": "...", "best_in_life": "..."}
            $table->json('self_portrait')->nullable(); 

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
            $table->unsignedTinyInteger('zodiac_sign')->default(0);

            // === ЛИЧНЫЕ ДАННЫЕ (Множественный выбор -> JSON) ===
            $table->json('body_decorations')->nullable(); 
            $table->json('languages')->nullable();
            $table->json('sports')->nullable();

            // === РАБОТА И ОБРАЗОВАНИЕ ===
            $table->string('education')->nullable();
            $table->string('occupation')->nullable();
            $table->string('institution')->nullable();
            $table->unsignedSmallInteger('institution_year')->nullable();
            $table->string('activity')->nullable();
            $table->string('position')->nullable();

            // === ГЕОЛОКАЦИЯ (PostGIS) ===
            $table->geography('location', subtype: 'point')->nullable();
            $table->string('address')->nullable();

            $table->timestamps();

            // === ИНДЕКСЫ ===
            $table->index('body_type');
            $table->index('smoking');
            $table->index('relationship_status');
            $table->index('education');
            $table->spatialIndex('location'); // Для молниеносного ST_DWithin
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};