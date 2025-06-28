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
        Schema::create('abonos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')
                ->constrained('prestamos');
            $table->decimal('monto_abono', 12, 2)->default(0);
            $table->enum('tipo_abono', ['capital', 'cuota'])
                ->default('cuota');
            $table->date('fecha_abono')->nullable();
            $table->smallInteger('numero_cuota')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abonos');
    }
};
