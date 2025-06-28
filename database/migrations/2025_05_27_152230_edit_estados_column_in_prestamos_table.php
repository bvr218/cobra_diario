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
            // Agregamos el nuevo estado 'archivado' a la lista existente
            // y mantenemos el default si sigue siendo apropiado.
            $table->enum('estado', ['pendiente', 'autorizado', 'negado', 'activo', 'finalizado'])
                ->default('pendiente')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            // Revertimos al estado anterior, sin 'archivado'.
            // Es importante que esta lista coincida con el estado de la columna
            // ANTES de que esta migración (add_archivado_to_estado_in_prestamos_table) se ejecutara.
            $table->enum('estado', ['pendiente', 'autorizado', 'negado', 'activo'])
                ->default('pendiente')->change();
        });
    }
};
