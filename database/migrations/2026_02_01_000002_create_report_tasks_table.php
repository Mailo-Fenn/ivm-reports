<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('report_tasks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('report_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('plan')->default('');
            $t->string('fact')->default('');
            $t->string('status')->default('выполнено');
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('report_tasks'); }
};
