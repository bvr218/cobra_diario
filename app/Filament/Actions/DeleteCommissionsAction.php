<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Services\StatsService;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon; // Ya estaba importado, pero lo mantengo por si acaso

class DeleteCommissionsAction
{
    protected StatsService $statsService;

    public function __construct(StatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    /**
     * Ejecuta la acción de "eliminar" comisiones para un usuario y un rango de fechas/horas.
     *
     * @param User $usuarioSeleccionado El usuario cuyas comisiones se van a "eliminar".
     * @param bool $filtrarPorFecha Indica si se deben aplicar los filtros de fecha/hora.
     * @param string|null $fechaInicio La fecha y hora de inicio del filtro (puede ser null).
     * @param string|null $fechaFin La fecha y hora de fin del filtro (puede ser null).
     */
    public function execute(User $usuarioSeleccionado, bool $filtrarPorFecha, ?string $fechaInicio, ?string $fechaFin): void
    {
        // Validación: Asegurarse de que un usuario esté seleccionado
        if (! $usuarioSeleccionado) {
            Notification::make()
                ->title('Error al eliminar seguros')
                ->body('Debes seleccionar un usuario para procesar sus seguros.')
                ->danger()
                ->send();
            return;
        }

        // Llamar al servicio para "eliminar" las comisiones
        // Se pasan las fechas y horas tal como vienen, ya que el StatsService se encarga de parsearlas.
        $deletedCount = $this->statsService->deleteUserCommissions(
            $usuarioSeleccionado,
            $filtrarPorFecha ? $fechaInicio : null,
            $filtrarPorFecha ? $fechaFin : null
        );

        // Enviar notificación al usuario sobre el resultado
        if ($deletedCount > 0) {
            Notification::make()
                ->title('Seguros eliminados')
                ->body("Se marcaron como eliminados **{$deletedCount}** seguros para **{$usuarioSeleccionado->name}**.")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('No se encontraron seguros')
                ->body('No se encontraron seguros por eliminar en el rango de fechas/horas seleccionado para este usuario.')
                ->warning()
                ->send();
        }

        // ---
        // IMPORTANTE: Si esta acción es llamada desde un componente Livewire (como RegistroAbonos),
        // y ese componente necesita actualizar su estado (e.g., recalcular estadísticas)
        // después de que esta acción se complete, deberías despachar un evento Livewire.
        // Por ejemplo, si lo llamas desde RegistroAbonos, y RegistroAbonos tiene un listener
        // para 'commissionsDeleted' que llama a computeStats(), entonces haz:
        //
        // \Livewire\Livewire::dispatch('commissionsDeleted');
        //
        // O si estás seguro de que siempre será desde un componente Livewire, y tienes acceso
        // a la instancia del componente, podrías usar $this->dispatch en el componente.
        // Dado que esta es una clase de acción y no un componente Livewire en sí,
        // usar el facade de Livewire es la forma más segura si el dispatch es necesario
        // para la interacción entre componentes o para notificar al que lo llamó.
        // ---
    }
}