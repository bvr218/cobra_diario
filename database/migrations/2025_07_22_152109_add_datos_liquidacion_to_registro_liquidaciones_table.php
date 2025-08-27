<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('registro_liquidaciones', function (Blueprint $table) {
            $table->json('datos_liquidacion')->nullable()->after('hasta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registro_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('datos_liquidacion');
        });
    }
};
