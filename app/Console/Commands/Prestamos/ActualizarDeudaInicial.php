<?php

namespace App\Console\Commands\Prestamos;

use App\Models\Prestamo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ActualizarDeudaInicial extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prestamos:actualizar-deuda-inicial';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza la columna deuda_inicial para todos los préstamos existentes, utilizando la lógica del evento saving del modelo.';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Iniciando la actualización de la columna deuda_inicial para los préstamos existentes...');

        $prestamoCount = Prestamo::count();

        if ($prestamoCount === 0) {
            $this->info('No hay préstamos para actualizar.');
            return Command::SUCCESS;
        }

        $progressBar = $this->output->createProgressBar($prestamoCount);
        $progressBar->start();

        $updatedCount = 0;
        $errorCount = 0;

        // Usamos chunkById para procesar en lotes, lo que es más eficiente en memoria.
        // Eager load 'refinanciamientos' ya que el evento 'saving' los utiliza.
        Prestamo::with('refinanciamientos')->chunkById(200, function ($prestamos) use ($progressBar, &$updatedCount, &$errorCount) {
            foreach ($prestamos as $prestamo) {
                try {
                    // La lógica para calcular 'deuda_inicial' está en el evento 'saving' del modelo Prestamo.
                    // Al guardar el modelo, este evento se disparará y actualizará el campo.
                    // Envolvemos cada guardado en una transacción por si el evento 'saving' o los observers
                    // realizan múltiples operaciones de base de datos que deben ser atómicas.
                    DB::transaction(function () use ($prestamo) {
                        $prestamo->save();
                    });
                    $updatedCount++;
                } catch (Throwable $e) {
                    $this->error("\nError al actualizar el préstamo ID {$prestamo->id}: " . $e->getMessage());
                    $errorCount++;
                }
                $progressBar->advance();
            }
        });

        $progressBar->finish();
        $this->info("\nActualización completada.");
        $this->info("Préstamos procesados exitosamente: " . $updatedCount);
        if ($errorCount > 0) {
            $this->warn("Préstamos con errores: " . $errorCount);
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
