<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;
use Filament\Notifications\Notification;
use Livewire\Attributes\On; // Importar el atributo On

class AdjustMoneyModal extends Component
{
    public bool $showAdjustMoneyModal = false;
    public ?float $amountToAdjust = null;
    public ?int $userId = null; // Para guardar el ID del usuario seleccionado
    public ?string $usuarioSeleccionadoName = ''; // Para mostrar en el modal

    protected \App\Filament\Actions\AdjustDineroBaseAction $adjustDineroBaseAction; // Asegúrate de importar la clase correcta

    public function boot(\App\Filament\Actions\AdjustDineroBaseAction $adjustDineroBaseAction): void
    {
        $this->adjustDineroBaseAction = $adjustDineroBaseAction;
    }

    #[On('openAdjustMoneyModal')] // Escucha el evento 'openAdjustMoneyModal'
    public function openModal(int $userId): void
    {
        $this->userId = $userId;
        $user = User::find($userId);
        $this->usuarioSeleccionadoName = $user ? $user->name : '';
        $this->amountToAdjust = null;
        $this->showAdjustMoneyModal = true;
    }

    public function closeModal(): void
    {
        $this->showAdjustMoneyModal = false;
        $this->reset(['amountToAdjust', 'userId', 'usuarioSeleccionadoName']);
    }

    public function adjustDineroBase(): void
    {
        $this->validate([
            'amountToAdjust' => 'required|numeric|not_in:0', // Permite números negativos, pero no cero
            'userId' => 'required|exists:users,id', // Asegurarse de que el usuario exista
        ]);

        $user = User::find($this->userId);

        if (!$user) {
            Notification::make()
                ->title('Error')
                ->body('Usuario no encontrado.')
                ->danger()
                ->send();
            $this->closeModal();
            return;
        }

        $isPositive = $this->amountToAdjust > 0;
        $actualAmount = abs($this->amountToAdjust);

        $this->adjustDineroBaseAction->execute(
            $user,
            $actualAmount,
            $isPositive
        );

        Notification::make()
            ->title('Dinero base ajustado correctamente.')
            ->success()
            ->send();

        $this->dispatch('statsUpdated'); // Notifica al componente padre para recalcular estadísticas
        $this->closeModal();
    }

    public function render()
    {
        return view('livewire.adjust-money-modal');
    }
}