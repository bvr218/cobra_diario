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
            $table->enum('estado', ['pendiente', 'autorizado', 'negado','activo'])
                ->default('pendiente')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->enum('estado', ['sin prestamo', 'al dia', 'en gestion', 'vencido'])
            ->default('sin prestamo')->change();
        });
    }
};
