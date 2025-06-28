<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ControlSesion;

class ControlSesionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $dias = [
            'Lunes',
            'Martes',
            'Miércoles',
            'Jueves',
            'Viernes',
            'Sábado',
            'Domingo',
        ];

        foreach ($dias as $dia) {
            ControlSesion::create([
                'dia' => $dia,
                'hora_apertura' =>  '07:00',
                'hora_cierre' =>  '22:00',
                'cerrado_manual' => false,
            ]);
        }
    }
}
