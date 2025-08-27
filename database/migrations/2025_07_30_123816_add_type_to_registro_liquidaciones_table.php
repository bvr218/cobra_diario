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
            // Añade la nueva columna 'type'
            $table->string('type')->default('diario')->after('hasta'); // Puedes ajustar 'after' según tu preferencia
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('registro_liquidaciones', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};