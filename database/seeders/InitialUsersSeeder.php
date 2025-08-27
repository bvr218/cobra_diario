<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class InitialUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Crear el usuario de tipo Oficina
        $oficina = User::create([
            'name' => 'Usuario Oficina',
            'email' => 'oficina@oficina.com',
            'password' => Hash::make('12345678'), // ¡Recuerda cambiar esta contraseña!
            'oficina_id' => null, // Una oficina no se asigna a sí misma
        ]);
        $oficina->assignRole('oficina');

        // 2. Crear el usuario de tipo Agente y asignarlo a la oficina creada
        $agente = User::create([
            'name' => 'Usuario Agente',
            'email' => 'agente@agente.com',
            'password' => Hash::make('12345678'), // ¡Recuerda cambiar esta contraseña!
            'oficina_id' => $oficina->id, // Asignamos el agente a la oficina de arriba
        ]);
        $agente->assignRole('agente');

        // 3. Crear el usuario de tipo Supervisor
        $supervisor = User::create([
            'name' => 'Usuario Supervisor',
            'email' => 'supervisor@supervisor.com',
            'password' => Hash::make('12345678'), // ¡Recuerda cambiar esta contraseña!
            'oficina_id' => null, // O asígnalo a una oficina si es necesario
        ]);
        $supervisor->assignRole('supervisor');
    }
}