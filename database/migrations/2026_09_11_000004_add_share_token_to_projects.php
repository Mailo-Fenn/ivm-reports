<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // секрет ссылки для клиента: /share/{token} открывает проект без входа, только просмотр
            $table->string('share_token', 64)->nullable()->unique()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('share_token');
        });
    }
};
