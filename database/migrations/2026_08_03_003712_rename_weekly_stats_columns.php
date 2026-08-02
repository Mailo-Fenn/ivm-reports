<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_stats', function (Blueprint $table) {

            if (Schema::hasColumn('weekly_stats', 'subscribers')) {
                $table->dropColumn('subscribers');
            }

            if (Schema::hasColumn('weekly_stats', 'interactions')) {
                $table->dropColumn('interactions');
            }

        });
    }

    public function down(): void
    {
        Schema::table('weekly_stats', function (Blueprint $table) {

            $table->integer('subscribers')->nullable();
            $table->integer('interactions')->nullable();

        });
    }
};
