<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;
use App\Models\RegistroLiquidacion;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Services\StatsService;
use App\Filament\Actions\DeleteCommissionsAction;
use App\Filament\Actions\AdjustDineroBaseAction;
use Illuminate\Support\Carbon;
use App\Models\DineroBase; // Asegúrate de importar DineroBase
use Spatie\Permission\Models\Role;
use Livewire\Attributes\On; 

// Importar los Traits
use App\Filament\Pages\Concerns\ManagesUsers;
use App\Filament\Pages\Concerns\HandlesStatsCalculations;
use App\Filament\Pages\Concerns\ManagesModals;

class RegistroAbonos extends Component implements HasForms
{
    use InteractsWithForms, ManagesUsers, HandlesStatsCalculations, ManagesModals;

    // PROPIEDADES PÚBLICAS ESPECÍFICAS DE ESTE COMPONENTE
    public bool $showGuardarLiquidacionModal = false;
    public ?string $liquidacionNombre = null;
    public ?int $usuarioId = null;
    public bool $showTransferenciaDineroEnManoModal = false;
    public ?float $montoTransferenciaDineroEnMano = null;
    public bool $lockDateFilter = false; // Flag para bloquear selector de fechas

    public ?User $usuarioSeleccionado = null;

    protected $listeners = [
        'statsUpdated' => 'computeStats',
        'liquidacionGuardada' => 'computeStats',
        'dineroBaseAdjusted' => 'computeStats',
    ];

    protected $queryString = [
        'usuarioId'       => ['as' => 'u',  'except' => null],
        'filtrarPorFecha' => ['as' => 'f',  'except' => true],
        'fechaInicio'     => ['as' => 'fi', 'except' => null],
        'fechaFin'        => ['as' => 'ff', 'except' => null],
        'rolSeleccionado' => ['as' => 'r',  'except' => null],
        'search'          => ['except' => ''],
    ];

    public function mount(): void
    {
        if (! (auth()->user()?->can('registro.index') || auth()->user()?->can('registro.view') ?? false)) {
            abort(403, 'No tienes permiso para acceder a esta página.');
        }

        $this->initializeManagesUsers();
        
        // La lógica de fechas ahora está centralizada en este método, que respeta el permiso 'registro.view'.
        $this->initializeDatesBasedOnFilter();

        // Si tiene permiso registro.view, solo necesitamos bloquear el selector.
        // Las fechas ya se establecieron correctamente en el método de arriba.
        if (auth()->user()?->can('registro.view')) {
            $this->lockDateFilter = true;
        }

        // Resto de tu lógica de mount sin cambios…
        if ($this->usuarioId) {
            $user = User::find($this->usuarioId);
            if ($user) {
                $this->usuarioSeleccionado = $user;
                $this->rolSeleccionado = $user->hasRole('Oficina') ? 'Oficina' : 'Agente';
                $this->showList = false;
            }
        }

        if ($this->rolSeleccionado) {
            $this->refreshUsuarios();
        }

        // Se llama una sola vez al final para calcular las estadísticas con los datos iniciales.
        $this->computeStats();
    }


    // --- Métodos de acciones del componente ---

    public function deleteComisiones(): void
    {
        // Asegúrate de que un usuario esté seleccionado antes de intentar borrar
        if (! $this->usuarioSeleccionado) {
            Notification::make()
                ->title('Error')
                ->body('Debes seleccionar un usuario para eliminar los seguros.')
                ->danger()
                ->send();
            return;
        }

        Notification::make()
            ->warning()
            ->title('Confirmar Eliminación de Seguros') 
            ->body('¿Estás seguro de que quieres eliminar los seguros para el usuario seleccionado' .
                   ($this->filtrarPorFecha ? ' en el rango de fechas seleccionado' : '') . '?')
            ->actions([
                \Filament\Notifications\Actions\Action::make('confirm')
                    ->label('Sí, Eliminar')
                    ->color('danger')
                    ->button()
                    ->dispatch('confirmDeleteCommissions', [ // Este dispatch es clave
                        'usuarioId' => $this->usuarioSeleccionado->id, 
                        'filtrarPorFecha' => $this->filtrarPorFecha,
                        'fechaInicio' => $this->fechaInicio,
                        'fechaFin' => $this->fechaFin,
                    ]),
                \Filament\Notifications\Actions\Action::make('cancel')
                    ->label('Cancelar')
                    ->color('gray')
                    ->button()
                    ->close(),
            ])
            ->persistent()
            ->send();
    }

    #[On('confirmDeleteCommissions')] // Este atributo de Livewire 3.x escucha el evento
    public function confirmDeleteCommissions(
        int $usuarioId,
        bool $filtrarPorFecha,
        ?string $fechaInicio,
        ?string $fechaFin
    ): void
    {
        // Los parámetros ahora se reciben individualmente
        $usuarioSeleccionado = User::find($usuarioId);

        if (! $usuarioSeleccionado) {
            Notification::make()
                ->title('Error al eliminar seguros')
                ->body('Usuario no seleccionado o no encontrado (ID: ' . $usuarioId . ').')
                ->warning()
                ->send();
            return;
        }

        // Ejecutar la acción de eliminación de comisiones
        app(DeleteCommissionsAction::class)->execute(
            $usuarioSeleccionado,
            $filtrarPorFecha,
            $fechaInicio,
            $fechaFin
        );
        
        // Vuelve a calcular las estadísticas después de la eliminación.
        $this->computeStats(); 
    }

    public function openAdjustMoneyModal(): void
    {
        if (! $this->usuarioSeleccionado) {
            Notification::make()->title('Selecciona un usuario')->body('Por favor, selecciona un usuario para ajustar su dinero base.')->warning()->send();
            return;
        }
        $this->dispatch('openAdjustMoneyModal', userId: $this->usuarioSeleccionado->id)->to('adjust-money-modal');
    }

    public function openGuardarLiquidacionModal(): void
    {
        if (!$this->usuarioSeleccionado) {
            Notification::make()
                ->title('Error')
                ->body('Debes seleccionar un usuario para guardar la liquidación.')
                ->danger()
                ->send();
            return;
        }
        if (!$this->filtrarPorFecha || !$this->fechaInicio || !$this->fechaFin) {
            Notification::make()
                ->title('Error')
                ->body('Debes establecer un rango de fecha y hora (modo "Día Individual") para guardar la liquidación.')
                ->danger()
                ->send();
            return;
        }

        $this->dispatch('openGuardarLiquidacionModal',
            usuarioId: $this->usuarioSeleccionado->id,
            fechaInicio: $this->fechaInicio,
            fechaFin: $this->fechaFin
        )->to('guardar-liquidacion-modal');
    }

    // Abre el modal para la transferencia
    public function openTransferenciaDineroEnManoModal(): void
    {
        if (!$this->usuarioSeleccionado) {
            Notification::make()->title('Error')->body('Seleccione un usuario primero.')->danger()->send();
            return;
        }
        $this->montoTransferenciaDineroEnMano = null; // Limpiar valor anterior
        $this->resetErrorBag('montoTransferenciaDineroEnMano'); // Limpiar errores anteriores
        $this->showTransferenciaDineroEnManoModal = true;
    }

    // Cierra el modal de transferencia
    public function closeTransferenciaDineroEnManoModal(): void
    {
        $this->showTransferenciaDineroEnManoModal = false;
        $this->montoTransferenciaDineroEnMano = null;
        $this->resetErrorBag('montoTransferenciaDineroEnMano');
    }

    public function realizarTransferenciaDineroEnMano(): void
    {
        if (!$this->usuarioSeleccionado) {
            Notification::make()->title('Error')->body('No hay usuario seleccionado.')->danger()->send();
            return;
        }

        $this->validate([
            'montoTransferenciaDineroEnMano' => 'required|numeric|not_in:0',
        ]);

        $dineroBase = DineroBase::where('user_id', $this->usuarioSeleccionado->id)->first();

        if (!$dineroBase) {
            // Crear si no existe, aunque debería existir si el usuario tiene estadísticas
            $dineroBase = DineroBase::create([
                'user_id' => $this->usuarioSeleccionado->id,
                'monto' => 0,
                'dinero_en_mano' => 0,
                'monto_general' => 0, // Asegúrate de que estos tengan un valor por defecto si es necesario
                'monto_inicial' => 0,
            ]);
        }

        $transferAmount = (float) $this->montoTransferenciaDineroEnMano;

        // Lógica de transferencia:
        // Si $transferAmount es positivo, sale de 'monto' (caja) y va a 'dinero_en_mano'.
        // Si $transferAmount es negativo, sale de 'dinero_en_mano' y va a 'monto' (caja).
        $dineroBase->monto -= $transferAmount;
        $dineroBase->dinero_en_mano += $transferAmount;

        try {
            // Guardar solo los campos modificados para esta operación específica
            $dineroBase->save(['monto', 'dinero_en_mano']); // Esto disparará el DineroBaseObserver

            Notification::make()
                ->title('Transferencia Realizada')
                ->body('La transferencia entre Dinero en Caja y Dinero en Mano se completó.')
                ->success()
                ->send();

            // Cerrar el modal y limpiar después de una transferencia exitosa
            $this->closeTransferenciaDineroEnManoModal();
            $this->computeStats(); // Actualizar las estadísticas en la vista

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error en la Transferencia')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
            \Log::error("Error en transferencia dinero en mano para usuario {$this->usuarioSeleccionado->id}: {$e->getMessage()}");
        }
    }

    public function render()
    {
        return view('livewire.registro-abonos');
    }
}