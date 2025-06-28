<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Refinanciamiento;
use App\Models\Prestamo; // Asegúrate de importar el modelo Prestamo
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecalculateRefinanciamientoDeudaInteres extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'refinanciamientos:recalculate-deuda-interes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula y actualiza la columna deuda_refinanciada_interes para todos los refinanciamientos existentes, asignando la deuda_actual del préstamo.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando el recálculo de la deuda_refinanciada_interes para refinanciamientos existentes...');

        $refinanciamientos = Refinanciamiento::all();

        $totalRefinanciamientos = $refinanciamientos->count();
        $this->info("Se encontraron {$totalRefinanciamientos} refinanciamientos para procesar.");

        if ($totalRefinanciamientos === 0) {
            $this->info('No hay refinanciamientos para procesar.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totalRefinanciamientos);
        $bar->start();

        foreach ($refinanciamientos as $refinanciamiento) {
            try {
                $prestamo = Prestamo::find($refinanciamiento->prestamo_id);

                if ($prestamo) {
                    // La deuda_actual del préstamo ya incluye el 'total' de esta refinanciación.
                    // Por lo tanto, para los registros existentes, asignamos este valor directamente.
                    $deudaActualDelPrestamoQueIncluyeRefinanciamiento = (float) $prestamo->deuda_actual;

                    $nuevaDeudaRefinanciadaInteres = (int) round($deudaActualDelPrestamoQueIncluyeRefinanciamiento);

                    // Aseguramos que la deuda_refinanciada_interes no sea negativa
                    $nuevaDeudaRefinanciadaInteres = max($nuevaDeudaRefinanciadaInteres, 0);

                    if ($refinanciamiento->deuda_refinanciada_interes !== $nuevaDeudaRefinanciadaInteres) {
                        $refinanciamiento->deuda_refinanciada_interes = $nuevaDeudaRefinanciadaInteres;
                        $refinanciamiento->saveQuietly();
                        // Log::info("Refinanciamiento ID {$refinanciamiento->id}: deuda_refinanciada_interes actualizada a {$nuevaDeudaRefinanciadaInteres}");
                    }
                } else {
                    $this->warn("Refinanciamiento ID {$refinanciamiento->id}: Préstamo asociado con ID {$refinanciamiento->prestamo_id} no encontrado. No se pudo calcular deuda_refinanciada_interes.");
                    Log::warning("Refinanciamiento ID {$refinanciamiento->id}: Préstamo asociado con ID {$refinanciamiento->prestamo_id} no encontrado para recalcular deuda_refinanciada_interes.");
                }
            } catch (\Exception $e) {
                $this->error("Error al procesar refinanciamiento ID {$refinanciamiento->id}: " . $e->getMessage());
                Log::error("Error al recalcular deuda_refinanciada_interes para refinanciamiento ID {$refinanciamiento->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Recálculo de deuda_refinanciada_interes completado.');
        return Command::SUCCESS;
    }
}
