<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Añadir la nueva columna a la tabla 'prestamos'
        Schema::table('prestamos', function (Blueprint $table) {
            $table->timestamp('authorized_at')->nullable()->after('estado');
        });

        // Añadir la nueva columna a la tabla 'refinanciamientos'
        Schema::table('refinanciamientos', function (Blueprint $table) {
            $table->timestamp('authorized_at')->nullable()->after('estado');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revertir los cambios en la tabla 'prestamos'
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropColumn('authorized_at');
        });

        // Revertir los cambios en la tabla 'refinanciamientos'
        Schema::table('refinanciamientos', function (Blueprint $table) {
            $table->dropColumn('authorized_at');
        });
    }
};