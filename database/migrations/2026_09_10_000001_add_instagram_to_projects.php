<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // подключение Instagram — отдельное на каждый проект: токен выдаётся аккаунту клиента
            $table->string('instagram_username')->nullable()->after('youtube_channel');
            $table->string('instagram_user_id')->nullable()->after('instagram_username');
            $table->text('instagram_token')->nullable()->after('instagram_user_id');
            $table->timestamp('instagram_token_expires_at')->nullable()->after('instagram_token');
        });

        // сторис живут в API только 24 часа — копим их по расписанию, чтобы считать за месяц
        Schema::create('instagram_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('story_id', 64);
            $table->timestamp('published_at');
            $table->timestamps();
            $table->unique(['project_id', 'story_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_stories');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['instagram_username', 'instagram_user_id', 'instagram_token', 'instagram_token_expires_at']);
        });
    }
};
