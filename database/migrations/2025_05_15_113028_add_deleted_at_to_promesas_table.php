<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeletedAtToPromesasTable extends Migration
{
    public function up(): void
    {
        Schema::table('promesas', function (Blueprint $table) {
            $table->softDeletes(); // crea la columna 'deleted_at' nullable
        });
    }

    public function down(): void
    {
        Schema::table('promesas', function (Blueprint $table) {
            $table->dropSoftDeletes(); // elimina la columna
        });
    }
}
