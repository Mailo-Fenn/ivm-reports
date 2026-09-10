<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // @имя, ссылка t.me или числовой id канала Telegram клиента
            $table->string('telegram_channel')->nullable()->after('instagram_token_expires_at');
        });

        // ежедневные снимки числа подписчиков: динамика для каналов, у которых нет встроенной статистики
        Schema::create('telegram_subscriber_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('taken_on');
            $table->unsignedInteger('subscribers');
            $table->timestamps();
            $table->unique(['project_id', 'taken_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_subscriber_snapshots');
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('telegram_channel');
        });
    }
};
