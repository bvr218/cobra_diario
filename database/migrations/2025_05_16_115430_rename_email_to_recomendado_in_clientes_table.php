<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameEmailToRecomendadoInClientesTable extends Migration
{
    public function up()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->renameColumn('email', 'recomendado');
        });
    }

    public function down()
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->renameColumn('recomendado', 'email');
        });
    }
}
