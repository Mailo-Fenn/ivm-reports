<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->text('summary')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
