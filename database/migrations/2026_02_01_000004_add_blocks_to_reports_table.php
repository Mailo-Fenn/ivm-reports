<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('reports', function (Blueprint $t) {
            $t->text('plan_next')->nullable();
            $t->text('community')->nullable();
            $t->json('business')->nullable();
        });
    }
    public function down(): void {
        Schema::table('reports', function (Blueprint $t) {
            $t->dropColumn(['plan_next', 'community', 'business']);
        });
    }
};
