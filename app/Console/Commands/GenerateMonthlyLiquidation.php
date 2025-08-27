<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\RegistroLiquidacion;
use Illuminate\Support\Carbon; // Asegúrate de que Carbon esté importado
use App\Services\StatsService; // Para las estadísticas de resumen
use App\Services\LiquidationDataCollectionService; // Para las listas detalladas

class GenerateMonthlyLiquidation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'liquidacion:mensual';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera y guarda automáticamente la liquidación mensual para agentes y oficina.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(StatsService $statsService, LiquidationDataCollectionService $dataCollectionService)
    {
        $this->info('Iniciando la generación de liquidaciones mensuales...');

        // Determinar el mes y año para la liquidación (el mes actual)
        // Se calcula sobre el mes en curso para que si se ejecuta el 31 de Julio,
        // se capturen los datos de Julio.
        $fechaActual = Carbon::now();

        // <<-- CAMBIO CLAVE AQUÍ: Usar ->copy() para evitar mutaciones no deseadas -->>
        $fechaInicioMes = $fechaActual->copy()->startOfMonth()->startOfDay(); // Crea una COPIA para el inicio del mes
        $fechaFinMes = $fechaActual->copy()->endOfMonth()->endOfDay();     // Crea OTRA COPIA para el fin del mes
        // <<----------------------------------------------------------------------->>

        // Obtener usuarios con los roles 'agente' y 'oficina'
        $users = User::role(['agente', 'oficina'])->get();

        if ($users->isEmpty()) {
            $this->warn('No se encontraron usuarios con los roles "agente" o "oficina".');
            return Command::SUCCESS;
        }

        foreach ($users as $user) {
            try {
                $this->info("Calculando liquidación para {$user->name} ({$user->id})...");

                // 1. Obtener las estadísticas de resumen
                $stats = $statsService->computeUserStats(
                    $user,
                    $fechaInicioMes->toDateTimeString(),
                    $fechaFinMes->toDateTimeString()
                );

                // 2. Obtener las listas detalladas
                $lists = $dataCollectionService->getDetailedLists(
                    $user,
                    $fechaInicioMes,
                    $fechaFinMes
                );

                // Combinar stats y lists en un solo array de datos_liquidacion
                $datosLiquidacion = array_merge($stats, [
                    'nombre_usuario' => $user->name,
                    'rol' => $user->getRoleNames()->first(), // Asume un solo rol principal
                    'fecha_guardado' => Carbon::now()->format('Y-m-d H:i:s'),
                    'listas_detalladas' => $lists, // Aquí guardamos todas las listas detalladas
                ]);

                // Guardar la liquidación
                RegistroLiquidacion::create([
                    'nombre' => 'Liquidación Mensual de ' . $user->name . ' (' . $fechaInicioMes->format('M Y') . ')',
                    'user_id' => $user->id,
                    'desde' => $fechaInicioMes,
                    'hasta' => $fechaFinMes,
                    'datos_liquidacion' => $datosLiquidacion,
                    'type' => 'mensual',
                ]);

                $this->info("Liquidación mensual guardada para {$user->name}.");

            } catch (\Exception $e) {
                $this->error("Error al generar liquidación para {$user->name}: " . $e->getMessage());
                \Log::error("Error en liquidación mensual automática para usuario {$user->id}: " . $e->getMessage(), ['exception' => $e]);
            }
        }

        $this->info('Generación de liquidaciones mensuales completada.');
        return Command::SUCCESS;
    }
}