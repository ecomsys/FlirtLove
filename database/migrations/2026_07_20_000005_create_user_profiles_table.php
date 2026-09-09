<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // === БАЗОВАЯ ИНФА ===
            $table->enum('gender', ['male', 'female'])->nullable()->index();
            
            // Храним только дату рождения. Индекс на ней дает максимальную скорость для фильтров
            $table->date('birth_date')->nullable()->index(); 
            
            $table->enum('dating_goal', ['friends', 'romantic', 'family', 'casual', 'travel'])->nullable()->index();
            
            // === СПРАВОЧНИКИ ===
            $table->foreignId('city_id')->nullable()->constrained('cities')->nullOnDelete()->index();
            $table->foreignId('country_id')->nullable()->constrained('countries')->nullOnDelete();
            
            // === ТЕКСТОВЫЕ БЛОКИ ===
            $table->string('headline')->nullable(); 
            $table->text('bio')->nullable(); 
            $table->text('looking_for')->nullable(); 

            // JSONB
            $table->jsonb('interests')->nullable(); 
            $table->jsonb('self_portrait')->nullable(); 

            // === ВНЕШНОСТЬ ===
            $table->unsignedTinyInteger('body_type')->default(0);
            $table->unsignedTinyInteger('eye_color')->default(0);
            $table->unsignedTinyInteger('hair_color')->default(0);
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('weight')->nullable();

            // === ЛИЧНЫЕ ДАННЫЕ ===
            $table->unsignedTinyInteger('relationship_status')->default(0);
            $table->unsignedTinyInteger('children_status')->default(0);
            $table->unsignedTinyInteger('pets')->default(0);
            $table->unsignedTinyInteger('housing')->default(0);
            $table->unsignedTinyInteger('has_car')->default(0);
            $table->unsignedTinyInteger('smoking')->default(0);
            $table->unsignedTinyInteger('alcohol')->default(0);
            $table->unsignedTinyInteger('zodiac_sign')->default(0);

            // === Множественный выбор -> JSONB ===
            $table->jsonb('body_decorations')->nullable(); 
            $table->jsonb('languages')->nullable();
            $table->jsonb('sports')->nullable();

            // === РАБОТА И ОБРАЗОВАНИЕ ===
            $table->string('education')->nullable();           
            $table->string('institution')->nullable();
            $table->unsignedSmallInteger('institution_year')->nullable();
            $table->string('activity')->nullable();
            $table->string('position')->nullable();

            // === ГЕОЛОКАЦИЯ (PostGIS) ===
            $table->geography('location', subtype: 'point')->nullable();
            $table->string('address')->nullable();

            $table->timestamps();
        });

        // === КРИТИЧЕСКИ ВАЖНЫЕ ИНДЕКСЫ ===
        
        // Составной индекс для ленты свайпов (Пол + Дата рождения)
        // База будет фильтровать по birth_date вместо age, что работает в 100 раз быстрее!
        DB::statement('CREATE INDEX user_profiles_gender_birthdate_index ON user_profiles (gender, birth_date)');

        // Индексы для частых фильтров
        DB::statement('CREATE INDEX user_profiles_body_type_index ON user_profiles (body_type)');
        DB::statement('CREATE INDEX user_profiles_smoking_index ON user_profiles (smoking)');
        DB::statement('CREATE INDEX user_profiles_relationship_status_index ON user_profiles (relationship_status)');
        DB::statement('CREATE INDEX user_profiles_education_index ON user_profiles (education)');
        
        // Пространственный индекс для геолокации
        DB::statement('CREATE INDEX user_profiles_location_sidx ON user_profiles USING GIST (location)');

        // GIN-индексы для мгновенного поиска по JSONB массивам
        DB::statement('CREATE INDEX user_profiles_interests_gin ON user_profiles USING GIN (interests jsonb_path_ops)');
        DB::statement('CREATE INDEX user_profiles_languages_gin ON user_profiles USING GIN (languages jsonb_path_ops)');
        DB::statement('CREATE INDEX user_profiles_sports_gin ON user_profiles USING GIN (sports jsonb_path_ops)');    
    }

    public function down(): void
    {
        Schema::dropIfExists('user_profiles');
    }
};