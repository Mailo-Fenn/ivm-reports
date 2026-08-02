<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('content_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained()->cascadeOnDelete();
            $t->string('platform', 16)->default('vk');
            $t->string('kind', 16)->default('post'); // post / reels / story
            $t->string('title');
            $t->unsignedBigInteger('views')->default(0);
            $t->unsignedInteger('reactions')->default(0);
            $t->unsignedInteger('comments')->default(0);
            $t->unsignedInteger('reposts')->default(0);
            $t->text('insight')->nullable();
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('content_items'); }
};
