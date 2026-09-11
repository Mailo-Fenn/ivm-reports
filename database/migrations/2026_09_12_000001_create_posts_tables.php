<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// контент-план: публикации проекта и результат их отправки в каждую соцсеть
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->text('text')->nullable();
            // [{path, type: image|video, name, size}] — файлы в storage/app/public/posts
            $table->json('media')->nullable();
            // площадки, куда публикуем: ["vk", "tg", "ig"]
            $table->json('platforms');
            $table->timestamp('scheduled_at')->nullable();
            // draft | scheduled | publishing | published | partial | failed
            $table->string('status', 16)->default('draft');
            $table->timestamps();
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('post_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 16);
            // pending | published | failed
            $table->string('status', 16)->default('pending');
            $table->string('external_id')->nullable();
            $table->string('url')->nullable();
            $table->text('error')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['post_id', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post_publications');
        Schema::dropIfExists('posts');
    }
};
