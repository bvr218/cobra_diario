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
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('primer_nombre');
            $table->string('segundo_nombre')->nullable();
            $table->string('primer_apellido');
            $table->string('segundo_apellido')->nullable();
            $table->string('foto_cliente')->nullable();
            $table->string('tipo_documento')->nullable();
            $table->string('telefono_1')->nullable();
            $table->string('telefono_2')->nullable();
            $table->string('email')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('dirección')->nullable();
            $table->string('coordenadas')->nullable();
            $table->string('foto_casa_cliente_1')->nullable();
            $table->string('foto_casa_cliente_2')->nullable();
            $table->string('foto_casa_cliente_3')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
