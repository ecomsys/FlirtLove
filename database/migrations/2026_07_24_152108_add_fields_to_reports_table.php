<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->softDeletes();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('resolved_at');
            $table->dropForeign(['moderator_id']);
            $table->dropColumn('moderator_id');
        });
    }
};