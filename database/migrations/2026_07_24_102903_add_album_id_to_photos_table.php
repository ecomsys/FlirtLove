<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            // Добавляем album_id, если его еще нет
            if (!Schema::hasColumn('photos', 'album_id')) {
                $table->foreignId('album_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete()
                    ->after('user_id');
            }

            // Добавляем индексы для быстрых запросов
            $table->index(['album_id', 'status']);
            $table->index(['album_id', 'is_primary']);
            $table->index(['album_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('photos', function (Blueprint $table) {
            $table->dropForeign(['album_id']);
            $table->dropColumn('album_id');

            $table->dropIndex(['album_id', 'status']);
            $table->dropIndex(['album_id', 'is_primary']);
            $table->dropIndex(['album_id', 'user_id']);
        });
    }
};