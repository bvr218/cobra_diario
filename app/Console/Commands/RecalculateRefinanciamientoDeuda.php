<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Refinanciamiento;
use App\Models\Prestamo; // Asegúrate de importar el modelo Prestamo
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateRefinanciamientoDeuda extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refinanciamientos:recalculate-deuda';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula y actualiza la columna deuda_refinanciada siguiendo la lógica: (deuda_actual del préstamo) - (total de refinanciamiento - valor de refinanciamiento).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando el recálculo de la deuda_refinanciada para refinanciamientos existentes...');

        // Obtenemos todos los refinanciamientos para procesar.
        $refinanciamientos = Refinanciamiento::all();

        $totalRefinanciamientos = $refinanciamientos->count();
        $this->info("Se encontraron {$totalRefinanciamientos} refinanciamientos para procesar.");

        $bar = $this->output->createProgressBar($totalRefinanciamientos);
        $bar->start();

        foreach ($refinanciamientos as $refinanciamiento) {
            try {
                // Asegúrate de que el refinanciamiento tenga un préstamo asociado
                $prestamo = Prestamo::find($refinanciamiento->prestamo_id);

                if ($prestamo) {
                    // Paso 1: Calcular 'resultado = total - valor' del refinanciamiento actual
                    $totalRefinanciamiento = (float) $refinanciamiento->total;
                    $valorRefinanciamiento = (float) $refinanciamiento->valor;
                    $resultado = $totalRefinanciamiento - $valorRefinanciamiento;

                    // Paso 2: Obtener la deuda actual del préstamo
                    $deudaActualPrestamo = (float) $prestamo->deuda_actual;

                    // Paso 3: Calcular la nueva deuda_refinanciada
                    // deuda_refinanciada = deuda_actual (del préstamo) - resultado
                    $nuevaDeudaRefinanciada = (int) round($deudaActualPrestamo - $resultado);

                    // Aseguramos que la deuda_refinanciada no sea negativa
                    $nuevaDeudaRefinanciada = max($nuevaDeudaRefinanciada, 0);

                    // Verificar si el valor ha cambiado para evitar escrituras innecesarias
                    if ($refinanciamiento->deuda_refinanciada !== $nuevaDeudaRefinanciada) {
                        $refinanciamiento->deuda_refinanciada = $nuevaDeudaRefinanciada;
                        // Usar saveQuietly() para no disparar el observer 'updated'
                        $refinanciamiento->saveQuietly();
                        // Log::info("Refinanciamiento ID {$refinanciamiento->id}: deuda_refinanciada actualizada a {$nuevaDeudaRefinanciada}");
                    }
                } else {
                    $this->warn("Refinanciamiento ID {$refinanciamiento->id}: Préstamo asociado con ID {$refinanciamiento->prestamo_id} no encontrado. No se pudo calcular deuda_refinanciada.");
                    Log::warning("Refinanciamiento ID {$refinanciamiento->id}: Préstamo asociado con ID {$refinanciamiento->prestamo_id} no encontrado para recalcular deuda_refinanciada.");
                }
            } catch (\Exception $e) {
                $this->error("Error al procesar refinanciamiento ID {$refinanciamiento->id}: " . $e->getMessage());
                Log::error("Error al recalcular deuda_refinanciada para refinanciamiento ID {$refinanciamiento->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Recálculo de deuda_refinanciada completado.');
    }
}