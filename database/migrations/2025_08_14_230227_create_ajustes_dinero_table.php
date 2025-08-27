<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_dinero', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // El usuario cuyo dinero se ajusta
            $table->foreignId('ajustado_por_id')->constrained('users')->cascadeOnDelete(); // El admin/oficina que hizo el ajuste
            $table->json('dinero_base_antes'); // Snapshot de las columnas relevantes antes del cambio
            $table->json('dinero_base_despues'); // Snapshot de las columnas relevantes después del cambio
            $table->decimal('monto_ajuste', 15, 2); // El valor que se ajustó
            $table->enum('tipo_ajuste', ['positivo', 'negativo']);
            $table->text('descripcion'); // La razón del ajuste (obligatoria)
            $table->timestamps(); // Para filtrar por rango de fechas
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_dinero');
    }
};