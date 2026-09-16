<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// MAX (мессенджер): бот агентства добавляется админом в каналы клиентов; список каналов
// узнаём только из событий бота, поэтому храним его у себя
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedBigInteger('max_channel_id')->nullable()->after('telegram_channel');
            $table->string('max_channel_title')->nullable()->after('max_channel_id');
        });

        // каналы и чаты, куда добавлен бот MAX
        Schema::create('max_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id')->unique();
            $table->string('title')->nullable();
            $table->string('link')->nullable();
            $table->boolean('is_channel')->default(true);
            $table->unsignedInteger('participants_count')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // ежедневные снимки подписчиков по площадкам без истории (MAX и другие в будущем)
        Schema::create('channel_subscriber_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16);
            $table->date('taken_on');
            $table->unsignedInteger('subscribers');
            $table->timestamps();
            $table->unique(['project_id', 'platform', 'taken_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_subscriber_snapshots');
        Schema::dropIfExists('max_chats');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['max_channel_id', 'max_channel_title']);
        });
    }
};
