<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Prestamo;
use App\Models\DineroBase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ActualizarMontoGeneralUsuarios extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:actualizar-monto-general-usuarios {userId? : El ID del usuario específico a actualizar}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza el monto_general en dinero_bases para todos los usuarios o uno específico, basado en sus préstamos y refinanciamientos registrados.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->argument('userId');

        if ($userId) {
            $user = User::find($userId);
            if ($user) {
                $this->actualizarMontoParaUsuario($user);
                $this->info("Monto general actualizado para el usuario: {$user->name} (ID: {$user->id})");
            } else {
                $this->error("Usuario con ID {$userId} no encontrado.");
            }
        } else {
            $this->info('Actualizando monto_general para todos los usuarios...');
            // Usamos chunking para manejar grandes cantidades de usuarios eficientemente
            User::chunk(100, function ($users) {
                foreach ($users as $user) {
                    $this->actualizarMontoParaUsuario($user);
                    $this->line("Monto general actualizado para el usuario: {$user->name} (ID: {$user->id})");
                }
            });
            $this->info('Actualización completada para todos los usuarios.');
        }

        return Command::SUCCESS;
    }

    protected function actualizarMontoParaUsuario(User $user)
    {
        // Asegurarnos de que el usuario tenga un registro en DineroBase
        // El modelo User ya tiene un booted que crea DineroBase si no existe.
        // Pero por si acaso, o si se ejecuta antes de que el evento 'created' del User se dispare.
        $dineroBase = DineroBase::firstOrCreate(
            ['user_id' => $user->id],
            ['monto' => 0, 'monto_general' => 0] // Valores por defecto si se crea
        );

        $estadosPermitidos = ['autorizado', 'activo', 'finalizado'];

        // 1. Sumar valor_prestado_con_interes de los préstamos registrados por el usuario
        //    que estén en estado 'autorizado', 'activo' o 'finalizado'
        $totalValorPrestadoConInteres = Prestamo::where('registrado_id', $user->id)
                                        ->whereIn('estado', $estadosPermitidos)
                                        ->sum('valor_prestado_con_interes');

        // 2. Sumar el 'total' de los refinanciamientos asociados a los préstamos registrados por el usuario
        //    cuyos préstamos estén en estado 'autorizado', 'activo' o 'finalizado'
        $totalRefinanciamientos = DB::table('prestamos')
            ->join('refinanciamientos', 'prestamos.id', '=', 'refinanciamientos.prestamo_id')
            ->where('prestamos.registrado_id', $user->id)
            ->whereIn('prestamos.estado', $estadosPermitidos)
            ->sum('refinanciamientos.total');
            
        // El monto_general será la suma de ambos
        $nuevoMontoGeneral = $totalValorPrestadoConInteres + $totalRefinanciamientos;

        // Actualizar el monto_general en DineroBase
        // Usamos update directamente para evitar disparar observadores de DineroBase si no es necesario
        // o si esos observadores tienen otra lógica que no queremos aquí.
        // Si necesitas que los observadores de DineroBase se disparen, usa:
        // $dineroBase->monto_general = $nuevoMontoGeneral;
        // $dineroBase->save();
        DineroBase::where('user_id', $user->id)->update(['monto_general' => $nuevoMontoGeneral]);
    }
}
