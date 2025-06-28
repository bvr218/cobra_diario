<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Abono; // Asegúrate de que este sea el modelo correcto
use Carbon\Carbon;

class RestoreTodaySoftDeletedAbonos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'abonos:restore-today'; // El nombre de tu comando

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Restaura todos los registros de abonos que fueron soft deleted hoy, solo si su préstamo asociado existe.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        $restoredCount = 0;
        $skippedCount = 0;

        // Obtener los abonos soft deleted hoy
        // Incluimos withTrashed() en la relación prestamo para poder verificar si el préstamo está soft deleted también
        $abonosToRestore = Abono::onlyTrashed()
            ->whereDate('deleted_at', $today)
            ->with(['prestamo' => function ($query) {
                // Carga el préstamo, incluyendo los soft deleted, para que podamos verificar su existencia.
                $query->withTrashed();
            }])
            ->get();

        if ($abonosToRestore->isEmpty()) {
            $this->info('No se encontraron registros de abonos soft deleted hoy para restaurar.');
        } else {
            $this->info('Intentando restaurar registros de abonos soft deleted en la fecha de hoy (' . $today->toDateString() . '):');
            $this->newLine(); // Salto de línea para mejor legibilidad

            foreach ($abonosToRestore as $abono) {
                // Verificar si el préstamo asociado existe (y no es null)
                // y si el préstamo NO está soft deleted (es decir, está activo).
                // Si el préstamo está soft deleted pero queremos restaurar el abono, esta lógica debería cambiar
                // para también restaurar el préstamo o solo advertir.
                // Para este caso, solo restauramos si el préstamo está "activo" (no soft deleted).
                if ($abono->prestamo && !$abono->prestamo->trashed()) {
                    try {
                        $abono->restore();
                        $restoredCount++;
                        $this->line("✔ Abono ID: {$abono->id} restaurado correctamente.");
                    } catch (\Exception $e) {
                        $this->error("✘ Error al restaurar Abono ID: {$abono->id}. Error: " . $e->getMessage());
                    }
                } else {
                    $skippedCount++;
                    // Mensaje más descriptivo sobre por qué se salta
                    if ($abono->prestamo_id && $abono->prestamo) {
                        $this->warn("⚠ Abono ID: {$abono->id} (prestamo_id: {$abono->prestamo_id}) no restaurado porque su préstamo asociado está soft deleted. ");
                    } elseif ($abono->prestamo_id) {
                        $this->warn("⚠ Abono ID: {$abono->id} (prestamo_id: {$abono->prestamo_id}) no restaurado porque su préstamo asociado no existe.");
                    } else {
                        $this->warn("⚠ Abono ID: {$abono->id} no restaurado porque no tiene prestamo_id.");
                    }
                }
            }
            $this->newLine(); // Salto de línea
            $this->info("Resumen:");
            $this->info("  Total de abonos soft deleted hoy: " . count($abonosToRestore));
            $this->info("  Registros de abonos restaurados: {$restoredCount}");
            $this->info("  Registros de abonos omitidos: {$skippedCount}");
        }

        return Command::SUCCESS;
    }
}