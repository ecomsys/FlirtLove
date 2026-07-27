<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Счетчики и метрики
            $table->unsignedInteger('profile_views')->default(0)->after('superlikes_remaining');
            $table->unsignedInteger('likes_count')->default(0)->after('profile_views');
            
            // Временные метки
            $table->timestamp('last_seen')->nullable()->after('last_login_at');
            $table->timestamp('premium_expires_at')->nullable()->after('is_premium');
            
            // Доп. данные анкеты
            $table->unsignedInteger('height')->nullable()->after('dating_goal');
            $table->string('education')->nullable()->after('height');
            $table->string('occupation')->nullable()->after('education');
            $table->string('zodiac_sign')->nullable()->after('occupation');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'profile_views', 'likes_count', 'last_seen', 'premium_expires_at',
                'height', 'education', 'occupation', 'zodiac_sign'
            ]);
        });
    }
};