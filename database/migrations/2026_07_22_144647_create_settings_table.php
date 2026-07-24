<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            // 'site_name', 'max_photos', 'moderation_auto_approve
            $table->string('key')->unique();
            // значение (JSON)
            $table->text('value')->nullable();
            // 'general', 'moderation', 'email', 'social'
            $table->string('group')->default('general');
            $table->string('label')->nullable();
            // text, number, boolean, select, textarea
            $table->string('type')->default('text'); 
            // для select
            $table->json('options')->nullable(); 
            // boolean (доступно ли для фронта)
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};