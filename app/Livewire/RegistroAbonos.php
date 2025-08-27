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
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;
use Livewire\Attributes\On;
use App\Services\LiquidationDataCollectionService;

use App\Filament\Pages\Concerns\ManagesUsers;
use App\Filament\Pages\Concerns\HandlesStatsCalculations;
use App\Filament\Pages\Concerns\ManagesModals;

class RegistroAbonos extends Component implements HasForms
{
    use InteractsWithForms, ManagesUsers, HandlesStatsCalculations, ManagesModals;

    // --- PROPIEDADES MODIFICADAS ---
    // Se eliminaron 'showTransferenciaDineroEnManoModal' y 'montoTransferenciaDineroEnMano'
    public bool $showGuardarLiquidacionModal = false;
    public ?string $liquidacionNombre = null;
    public ?int $usuarioId = null;
    public bool $lockDateFilter = false; 

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
        
        $this->initializeDatesBasedOnFilter();

        if (auth()->user()?->can('registro.view')) {
            $this->lockDateFilter = true;
        }

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

        $this->computeStats();
    }


    // --- Métodos de acciones del componente ---

    public function deleteComisiones(): void
    {
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
                    ->dispatch('confirmDeleteCommissions', [ 
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

    #[On('confirmDeleteCommissions')] 
    public function confirmDeleteCommissions(
        int $usuarioId,
        bool $filtrarPorFecha,
        ?string $fechaInicio,
        ?string $fechaFin
    ): void
    {
        $usuarioSeleccionado = User::find($usuarioId);

        if (! $usuarioSeleccionado) {
            Notification::make()
                ->title('Error al eliminar seguros')
                ->body('Usuario no seleccionado o no encontrado (ID: ' . $usuarioId . ').')
                ->warning()
                ->send();
            return;
        }

        app(DeleteCommissionsAction::class)->execute(
            $usuarioSeleccionado,
            $filtrarPorFecha,
            $fechaInicio,
            $fechaFin
        );
        
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

        // Recolectamos las estadísticas actuales
        $statsActuales = [
            'dineroInicial' => $this->dineroInicial,
            'dineroCapital' => $this->dineroCapital,
            'dineroEnMano' => $this->dineroEnMano,
            'prestamosEntregados' => $this->prestamosEntregados,
            'prestamosPendientes' => $this->prestamosPendientes,
            'totalPrestado' => $this->totalPrestado,
            'totalPrestadoConInteres' => $this->totalPrestadoConInteres,
            'cantidadRefinanciaciones' => $this->cantidadRefinanciaciones,
            'cantidadRefinanciacionesPendientes' => $this->cantidadRefinanciacionesPendientes,
            'deudaRefinanciadaTotal' => $this->deudaRefinanciadaTotal,
            'montoRefinanciaciones' => $this->montoRefinanciaciones,
            'totalComision' => $this->totalComision,
            'prestamosFinalizadosCount' => $this->prestamosFinalizadosCount,
            'cantidadRecaudosRealizados' => $this->cantidadRecaudosRealizados,
            'totalPrestamosAsignados' => $this->totalPrestamosAsignados,
            'dineroRecaudado' => $this->dineroRecaudado,
            'gastosAutorizados' => $this->gastosAutorizados,
            'gastosNoAutorizados' => $this->gastosNoAutorizados,
            'dineroEnCaja' => $this->dineroEnCaja,
        ];

        $liquidationCollectionService = app(LiquidationDataCollectionService::class);
        $listasParaGuardar = $liquidationCollectionService->getDetailedLists(
            $this->usuarioSeleccionado,
            Carbon::parse($this->fechaInicio),
            Carbon::parse($this->fechaFin)
        );

        $this->dispatch('openGuardarLiquidacionModal',
            usuario: $this->usuarioSeleccionado,
            fechaInicio: $this->fechaInicio,
            fechaFin: $this->fechaFin,
            rolSeleccionado: $this->rolSeleccionado,
            stats: $statsActuales,
            listas: $listasParaGuardar,
            type: 'diario',
        )->to('guardar-liquidacion-modal');
    }

    public function render()
    {
        return view('livewire.registro-abonos');
    }
}