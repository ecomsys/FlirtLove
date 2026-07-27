<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            // Добавляем тип чата. По умолчанию 'private' (обычные чаты знакомств)
            $table->enum('type', ['private', 'support'])->default('private')->after('id');
            $table->index('type'); // Индекс для быстрой фильтрации
        });
    }

    public function down(): void
    {
        Schema::table('chats', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};