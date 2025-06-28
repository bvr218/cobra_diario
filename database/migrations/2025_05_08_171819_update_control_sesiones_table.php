<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('control_sesiones', function (Blueprint $table) {
            // Eliminamos columnas que ya no se usarán
            $table->dropColumn(['fecha_apertura', 'fecha_cierre']);

            // Agregamos las nuevas columnas
            $table->string('dia')->after('id');
            $table->time('hora_apertura')->nullable()->after('dia');
            $table->time('hora_cierre')->nullable()->after('hora_apertura');
        });
    }

    public function down(): void
    {
        Schema::table('control_sesiones', function (Blueprint $table) {
            // Revertimos: agregamos las antiguas y quitamos las nuevas
            $table->timestamp('fecha_apertura')->nullable();
            $table->timestamp('fecha_cierre')->nullable();

            $table->dropColumn(['dia', 'hora_apertura', 'hora_cierre']);
        });
    }
};
