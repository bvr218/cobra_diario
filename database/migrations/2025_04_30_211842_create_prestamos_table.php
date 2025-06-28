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
        Schema::create('prestamos', function (Blueprint $table) {
            $table->id();

            // Relación con clientes (no se puede eliminar si tiene préstamos)
            $table->foreignId('cliente_id')
                ->constrained('clientes')
                ->restrictOnDelete()
                ->cascadeOnUpdate();

            // Relación con users (agente asignado, se puede eliminar y queda null)
            $table->foreignId('agente_asignado')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            // Estado del préstamo
            $table->enum('estado', ['sin prestamo', 'al dia', 'en gestion', 'vencido'])
                ->default('sin prestamo');

            // Monto del préstamo
            $table->decimal('valor_total_prestamo', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prestamos');
    }
};
