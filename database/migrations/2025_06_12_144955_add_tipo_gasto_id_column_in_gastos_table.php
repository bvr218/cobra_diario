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
        Schema::table('gastos', function (Blueprint $table) {
            $table->foreignId('tipo_gasto_id')->nullable()->constrained('tipo_gastos')->onDelete('set null');
            $table->string('informacion')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropForeign(['tipo_gasto_id']);
            $table->dropColumn('tipo_gasto_id');
            $table->string('informacion')->nullable(false)->change();
        });
    }
};
