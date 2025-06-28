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
            $table->foreignId('frecuencia_id')
                ->constrained('frecuencias')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->date("initial_date");
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prestamos', function (Blueprint $table) {
            $table->dropForeign(['frecuencia_id']);
            $table->dropColumn('frecuencia_id');
            $table->dropColumn('initial_date');
            $table->dropColumn("deleted_at");

        });
    }
};
