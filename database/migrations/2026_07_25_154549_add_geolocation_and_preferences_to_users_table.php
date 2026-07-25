<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Геолокация (PostGIS)
            $table->geography('location', subtype: 'point')->nullable()->index();
            $table->float('latitude', 10, 7)->nullable();
            $table->float('longitude', 10, 7)->nullable();           
            $table->string('country')->nullable();

            // О себе и интересы
            $table->text('bio')->nullable();
            $table->json('interests')->nullable(); // массив строк

            // Предпочтения для поиска
            $table->unsignedInteger('preferred_age_min')->default(18);
            $table->unsignedInteger('preferred_age_max')->default(99);
            $table->string('preferred_gender')->default('any'); // male, female, any
            $table->unsignedInteger('preferred_distance_km')->default(50);

            // Премиум и верификация (на будущее)
            $table->boolean('is_premium')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->unsignedInteger('superlikes_remaining')->default(5);
            
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'location', 'latitude', 'longitude', 'country',
                'bio', 'interests',
                'preferred_age_min', 'preferred_age_max', 'preferred_gender', 'preferred_distance_km',
                'is_premium', 'is_verified', 'superlikes_remaining',                
            ]);
        });
    }
};