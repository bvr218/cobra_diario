<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;
use App\Models\DineroBase;
use App\Models\AjusteDinero;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class AdjustMoneyModal extends Component
{
    public bool $showAdjustMoneyModal = false;
    public ?float $amountToAdjust = null;
    public ?int $userId = null;
    public ?string $usuarioSeleccionadoName = '';
    public ?string $descripcion = null; // <-- NUEVA PROPIEDAD para la descripción

    #[On('openAdjustMoneyModal')]
    public function openModal(int $userId): void
    {
        $this->userId = $userId;
        $user = User::find($userId);
        $this->usuarioSeleccionadoName = $user ? $user->name : '';
        // Reseteamos los campos del formulario
        $this->reset(['amountToAdjust', 'descripcion']);
        $this->showAdjustMoneyModal = true;
    }

    public function closeModal(): void
    {
        $this->showAdjustMoneyModal = false;
        $this->reset(['amountToAdjust', 'userId', 'usuarioSeleccionadoName', 'descripcion']);
    }

    public function adjustDineroBase(): void
    {
        // === VALIDACIÓN ACTUALIZADA ===
        $this->validate([
            'amountToAdjust' => 'required|numeric|not_in:0',
            'descripcion'    => 'required|string|min:10|max:255',
            'userId'         => 'required|exists:users,id',
        ], [
            'descripcion.required' => 'La descripción es obligatoria.',
            'descripcion.min' => 'La descripción debe tener al menos 10 caracteres.',
            'amountToAdjust.required' => 'El monto es obligatorio.',
            'amountToAdjust.not_in' => 'El monto no puede ser cero.',
        ]);

        $user = User::find($this->userId);
        if (!$user) {
            Notification::make()->title('Error')->body('Usuario no encontrado.')->danger()->send();
            $this->closeModal();
            return;
        }

        try {
            // === LÓGICA DE AJUSTE Y AUDITORÍA DENTRO DE UNA TRANSACCIÓN ===
            DB::transaction(function () use ($user) {
                $dineroBase = DineroBase::firstOrCreate(
                    ['user_id' => $user->id],
                    ['monto' => 0, 'monto_general' => 0, 'monto_inicial' => 0, 'dinero_en_mano' => 0]
                );

                // 1. Capturar estado ANTES del cambio
                $estadoAntes = $dineroBase->only(['monto', 'monto_general', 'dinero_en_mano', 'monto_inicial']);

                // 2. Aplicar el cambio al dinero base
                $dineroBase->monto += $this->amountToAdjust;
                $dineroBase->monto_general += $this->amountToAdjust;

                if ($this->amountToAdjust > 0) {
                    $dineroBase->monto_inicial += $this->amountToAdjust;
                }
                
                $dineroBase->save();

                // 3. Capturar estado DESPUÉS del cambio (refrescando el modelo)
                $estadoDespues = $dineroBase->fresh()->only(['monto', 'monto_general', 'dinero_en_mano', 'monto_inicial']);

                // 4. Crear el registro de auditoría en la nueva tabla
                AjusteDinero::create([
                    'user_id'           => $user->id,
                    'ajustado_por_id'   => auth()->id(),
                    'dinero_base_antes' => $estadoAntes,
                    'dinero_base_despues' => $estadoDespues,
                    'monto_ajuste'      => $this->amountToAdjust,
                    'tipo_ajuste'       => $this->amountToAdjust > 0 ? 'positivo' : 'negativo',
                    'descripcion'       => $this->descripcion,
                ]);
            });

            Notification::make()
                ->title('Dinero base ajustado')
                ->body('El ajuste se ha realizado y registrado exitosamente.')
                ->success()
                ->send();

            $this->dispatch('dineroBaseAdjusted'); // Usamos un evento más específico
            $this->dispatch('statsUpdated'); // Mantenemos este por si otros componentes lo usan
            $this->closeModal();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al ajustar dinero')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        return view('livewire.adjust-money-modal');
    }
}