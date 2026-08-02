<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Неделя');
            $table->unsignedInteger('position')->default(0);
            $table->integer('subscribers')->default(0);      // прирост подписчиков за неделю
            $table->unsignedBigInteger('views')->default(0);        // просмотры
            $table->unsignedBigInteger('reach')->default(0);        // охваты
            $table->unsignedBigInteger('interactions')->default(0); // взаимодействия
            $table->unsignedInteger('leads')->default(0);           // заявки
            $table->unsignedInteger('posts')->default(0);           // выложенные посты
            $table->unsignedInteger('stories')->default(0);         // выложенные сторис
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_stats');
    }
};
