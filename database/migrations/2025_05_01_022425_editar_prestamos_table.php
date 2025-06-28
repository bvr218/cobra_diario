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
        Schema::table('prestamos', function (Blueprint $table) {
            // Deuda actual, con 2 decimales y hasta 13 enteros (ajusta a tu conveniencia)
            $table->decimal('deuda_actual', 12, 2)
                  ->default(0)
                  ->after('valor_total_prestamo');

            // Interés como porcentaje (p. ej. 5.25%)
            $table->decimal('interes', 5, 2)
                  ->default(0)
                  ->after('deuda_actual');

            // Número de cuotas pactadas
            $table->smallInteger('numero_cuotas')
                  ->unsigned()
                  ->default(1)
                  ->after('interes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropColumn('numero_cuotas');
            $table->dropColumn('interes');
            $table->dropColumn('deuda_actual');
        });
    }
};
