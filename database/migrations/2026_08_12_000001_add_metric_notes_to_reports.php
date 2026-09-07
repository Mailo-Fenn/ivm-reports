<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// выводы по площадкам для слайдов презентации: {vk: {subs: [..], views: [..], inter: [..]}, ...}
return new class extends Migration {
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $t) {
            $t->text('metric_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $t) {
            $t->dropColumn('metric_notes');
        });
    }
};
