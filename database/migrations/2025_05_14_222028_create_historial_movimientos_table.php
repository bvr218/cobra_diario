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
        Schema::create('historial_movimientos', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->string('tipo'); // ejemplo: abono, ajuste, gasto
            $table->text('descripcion')->nullable();
            $table->bigInteger('monto');
            $table->timestamp('fecha');

            $table->boolean('es_edicion')->default(false);
            $table->json('cambio_desde')->nullable();
            $table->json('cambio_hacia')->nullable();

            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->string('tabla_origen')->nullable();

            $table->timestamps();

            // Clave foránea SIN delete en cascada
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('historial_movimientos');
    }
};
