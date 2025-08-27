<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Frecuencia;

class FrecuenciaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $frecuencias = [
            ['name' => 'Diario', 'dias' => 1],
            ['name' => 'Semanal', 'dias' => 7],
            ['name' => 'Quincenal', 'dias' => 15],
            ['name' => 'Mensual', 'dias' => 30],
            ['name' => 'Trimestral', 'dias' => 90],
            ['name' => 'Semestral', 'dias' => 180],
            ['name' => 'Anual', 'dias' => 365],
        ];

        foreach ($frecuencias as $frecuencia) {
            Frecuencia::create($frecuencia);
        }
    }
}