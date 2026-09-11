<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// is_enabled у площадки отчёта: null — «авто» (ВК/Инстаграм/Макс включены, YouTube/Telegram —
// при заполненном канале или наличии цифр), true/false — явный выбор тумблером в редакторе
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_stats', function (Blueprint $table) {
            $table->boolean('is_enabled')->nullable()->default(null)->change();
        });
        // раньше поле нигде не выставлялось — все существующие значения считаем «авто»
        DB::table('platform_stats')->update(['is_enabled' => null]);
    }

    public function down(): void
    {
        DB::table('platform_stats')->whereNull('is_enabled')->update(['is_enabled' => true]);
        Schema::table('platform_stats', function (Blueprint $table) {
            $table->boolean('is_enabled')->default(true)->nullable(false)->change();
        });
    }
};
