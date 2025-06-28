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
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn("primer_nombre");
            $table->dropColumn("segundo_nombre");
            $table->dropColumn("primer_apellido");
            $table->dropColumn("segundo_apellido");
            $table->dropColumn("telefono_1");
            $table->dropColumn("telefono_2");
            $table->dropColumn("foto_casa_cliente_1");
            $table->dropColumn("foto_casa_cliente_2");
            $table->dropColumn("foto_casa_cliente_3");


            $table->string("nombre");
            $table->json("telefonos")->nullable();
            $table->json("galeria")->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn("nombre");
            $table->dropColumn("telefonos");
            $table->dropColumn("galeria");

            $table->string('primer_nombre');
            $table->string('segundo_nombre')->nullable();
            $table->string('primer_apellido');
            $table->string('segundo_apellido')->nullable();
            $table->string('telefono_1')->nullable();
            $table->string('telefono_2')->nullable();
            $table->string('foto_casa_cliente_1')->nullable();
            $table->string('foto_casa_cliente_2')->nullable();
            $table->string('foto_casa_cliente_3')->nullable();

        });
    }
};
