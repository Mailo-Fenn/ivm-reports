<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('platform_stats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained()->cascadeOnDelete();
            $t->string('platform', 16);            // vk / ig / max / tg
            $t->integer('subs')->default(0);
            $t->unsignedBigInteger('views')->default(0);
            $t->unsignedBigInteger('reach')->default(0);
            $t->unsignedBigInteger('inter')->default(0);
            $t->unsignedInteger('leads')->default(0);
            $t->unsignedInteger('posts')->default(0);
            $t->unsignedInteger('stories')->default(0);
            $t->timestamps();
            $t->unique(['report_id', 'platform']);
        });
    }
    public function down(): void { Schema::dropIfExists('platform_stats'); }
};
