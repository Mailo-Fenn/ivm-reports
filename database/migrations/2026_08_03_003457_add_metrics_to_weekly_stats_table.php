<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_stats', function (Blueprint $table) {

            if (!Schema::hasColumn('weekly_stats', 'subs')) {
                $table->integer('subs')->default(0);
            }

            if (!Schema::hasColumn('weekly_stats', 'inter')) {
                $table->integer('inter')->default(0);
            }

        });
    }

    public function down(): void
    {
        Schema::table('weekly_stats', function (Blueprint $table) {
            $table->dropColumn([
                'platform',
                'subs',
                'views',
                'reach',
                'inter',
                'leads',
                'posts',
                'stories',
            ]);
        });
    }
};
