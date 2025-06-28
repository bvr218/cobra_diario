<?php

namespace App\Filament\Actions;

use App\Models\User;
use App\Models\HistorialMovimiento; // Todavía necesaria para registrar el movimiento
use App\Services\StatsService;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class AdjustDineroBaseAction
{
    protected StatsService $statsService;

    public function __construct(StatsService $statsService)
    {
        $this->statsService = $statsService;
    }

    public function execute(User $usuarioSeleccionado, ?float $amountToAdjust, bool $isPositiveAdjustment): void
    {
        if (! $usuarioSeleccionado) {
            Notification::make()
                ->title('Error')
                ->body('No hay un usuario seleccionado.')
                ->danger()
                ->send();
            return;
        }

        // El componente Livewire ahora envía el valor absoluto y se asegura de que no sea cero.
        // Esta validación asegura que el monto sea numérico y positivo (ya que es el valor absoluto).
        if (!is_numeric($amountToAdjust) || $amountToAdjust <= 0) { // amountToAdjust aquí es el valor absoluto
            Notification::make()
                ->title('Error de Monto')
                ->body('Por favor, ingresa un monto numérico válido y diferente de cero.')
                ->danger()
                ->send();
            return;
        }

        $user = $usuarioSeleccionado;

        // Llama a la lógica existente en StatsService para ajustar el dinero base (monto y monto_general).
        // Esta línea se mantiene como estaba originalmente.
        $newDineroBases = $this->statsService->adjustUserDineroBase($user, $amountToAdjust, $isPositiveAdjustment);

        // --- INICIO DE LA NUEVA LÓGICA PARA monto_inicial ---

        // Acceder al registro de DineroBase del usuario
        $dineroBaseRecord = $user->dineroBase; // Asumiendo que user tiene una relación hasOne con DineroBase

        if (!$dineroBaseRecord) {
            Notification::make()
                ->title('Error')
                ->body('No se encontró el registro de dinero base para este usuario. No se pudo actualizar el monto inicial.')
                ->danger()
                ->send();
            return;
        }

        // Suma a monto_inicial SOLO si el ajuste es POSITIVO
        if ($isPositiveAdjustment) {
            $dineroBaseRecord->monto_inicial += $amountToAdjust;
            $dineroBaseRecord->save(); // Guarda el cambio en monto_inicial
        }
        // Si $isPositiveAdjustment es false (ajuste negativo), no hacemos nada con monto_inicial.

        // --- FIN DE LA NUEVA LÓGICA PARA monto_inicial ---


        Notification::make()
            ->title('Dinero Base Ajustado')
            ->body("El dinero base de {$user->name} ha sido ajustado en " . ($isPositiveAdjustment ? '+' : '-') . "$" . number_format($amountToAdjust, 0, ',', '.') . ". Nuevo saldo: $" . number_format($newDineroBases, 0, ',', '.'))
            ->success()
            ->send();

        // Puedes emitir un evento si necesitas que el componente principal se entere del cambio.
        // Por ejemplo:
        // $this->dispatch('dineroBaseAdjusted');
    }
}