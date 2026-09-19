<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Models\User;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Добавляем колонку (пока без индекса, чтобы не блокировать таблицу)
        Schema::table('users', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });

        // 2. Заполняем слаги для существующих юзеров (если таблица не пустая)
        User::whereNull('slug')->chunkById(200, function ($users) {
            foreach ($users as $user) {
                $baseSlug = Str::slug($user->name) ?: 'user';
                $user->slug = $baseSlug . '-' . Str::lower(Str::random(5));
                $user->save();
            }
        });

        // 3. Теперь безопасно вешаем уникальный индекс
        Schema::table('users', function (Blueprint $table) {
            $table->unique('slug');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};