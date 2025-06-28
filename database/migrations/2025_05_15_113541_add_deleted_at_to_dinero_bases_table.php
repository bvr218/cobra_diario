<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeletedAtToDineroBasesTable extends Migration
{
    public function up(): void
    {
        Schema::table('dinero_bases', function (Blueprint $table) {
            $table->softDeletes(); // agrega deleted_at
        });
    }

    public function down(): void
    {
        Schema::table('dinero_bases', function (Blueprint $table) {
            $table->dropSoftDeletes(); // elimina deleted_at
        });
    }
}
