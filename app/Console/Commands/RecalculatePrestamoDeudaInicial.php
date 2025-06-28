<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Prestamo; // Asegúrate de importar el modelo Prestamo
use Illuminate\Support\Facades\DB; // No es estrictamente necesario si solo usas Eloquent save(), pero buena práctica
use Illuminate\Support\Facades\Log; // Para mensajes de error detallados

class RecalculatePrestamoDeudaInicial extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prestamos:recalculate-deuda-inicial';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula y actualiza la columna deuda_inicial para todos los préstamos existentes según la nueva lógica de refinanciamientos (valor_prestado_con_interes o deuda_refinanciada_interes del último autorizado).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando el recálculo de deuda_inicial para préstamos existentes...');

        // Carga los préstamos con sus refinanciamientos para evitar problemas N+1
        // y asegurarnos de que la relación 'refinanciamientos' esté disponible.
        $prestamos = Prestamo::with('refinanciamientos')->get();

        $totalPrestamos = $prestamos->count();
        $this->info("Se encontraron {$totalPrestamos} préstamos para procesar.");

        if ($totalPrestamos === 0) {
            $this->info('No hay préstamos para procesar.');
            return Command::SUCCESS;
        }

        $bar = $this->output->createProgressBar($totalPrestamos);
        $bar->start();

        foreach ($prestamos as $prestamo) {
            try {
                // Obtén el último refinanciamiento autorizado para este préstamo
                // Usamos la relación ya cargada para eficiencia
                $ultimoRefinanciamientoAutorizado = $prestamo->refinanciamientos
                                                              ->where('estado', 'autorizado')
                                                              ->sortByDesc('id') // Asumiendo que el ID descendente indica el último
                                                              ->first();

                $nuevaDeudaInicial = 0;

                if ($ultimoRefinanciamientoAutorizado) {
                    // Si hay un refinanciamiento autorizado, la deuda_inicial es la deuda_refinanciada_interes de ese refinanciamiento
                    $nuevaDeudaInicial = (int) round($ultimoRefinanciamientoAutorizado->deuda_refinanciada_interes);
                } else {
                    // Si no hay refinanciamientos autorizados, la deuda_inicial es el valor_prestado_con_interes original
                    $nuevaDeudaInicial = (int) round($prestamo->valor_prestado_con_interes);
                }

                // Si el valor calculado es diferente al actual en la DB, actualízalo
                if ($prestamo->deuda_inicial !== $nuevaDeudaInicial) {
                    // Asignamos el nuevo valor. Al llamar a save(), se disparará el evento 'saving'
                    // en el modelo Prestamo, donde ya tienes la lógica para setear 'deuda_inicial'.
                    // Esto asegura que la lógica del 'booted' se aplique.
                    // Para evitar que el 'booted' recalcule *de nuevo* si ya lo hicimos aquí,
                    // podrías usar saveQuietly(), pero en este caso, es mejor dejarlo que se revalide.
                    // La asignación explícita aquí es solo para hacer el intent claro.
                    $prestamo->deuda_inicial = $nuevaDeudaInicial;
                    $prestamo->save(); // Dispara el evento 'saving' y guarda el modelo
                }

            } catch (\Exception $e) {
                $this->error("Error al procesar préstamo ID {$prestamo->id}: " . $e->getMessage());
                Log::error("Error al recalcular deuda_inicial para préstamo ID {$prestamo->id}: " . $e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Recálculo de deuda_inicial para préstamos completado exitosamente.');
        return Command::SUCCESS;
    }
}