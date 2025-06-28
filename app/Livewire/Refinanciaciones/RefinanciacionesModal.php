<?php

namespace App\Livewire\Refinanciaciones;

use Livewire\Component;
use App\Models\Refinanciamiento;
use App\Models\Prestamo;
use App\Models\Cliente;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Filament\Resources\PrestamoResource;
use Filament\Notifications\Notification; // Importamos la clase Notification

class RefinanciacionesModal extends Component
{
    public bool $showModal = false;
    public ?int $usuarioId = null;
    public ?string $fechaInicio = null;
    public ?string $fechaFin = null;
    public Collection $refinanciaciones;
    public ?string $modalType = null;
    public string $modalTitle = '';

    protected $listeners = [
        'abrirModalCantidadRefinanciaciones' => 'openModal',
        'abrirModalValorTotalRefinanciaciones' => 'openModal',
        'abrirModalValorRefinanciacionesConInteres' => 'openModal',
    ];

    public function mount(): void
    {
        $this->refinanciaciones = collect();
    }

    public function openModal(int $usuarioId, ?string $fechaInicio = null, ?string $fechaFin = null, string $type): void
    {
        $this->usuarioId = $usuarioId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->modalType = $type;

        $this->setModalTitle($type);
        $this->loadRefinanciaciones();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['usuarioId', 'fechaInicio', 'fechaFin', 'modalType', 'modalTitle']);
        $this->refinanciaciones = collect();
    }

    protected function setModalTitle(string $type): void
    {
        switch ($type) {
            case 'cantidad':
                $this->modalTitle = 'Información de cantidad de Refinanciaciones';
                break;
            case 'valor_total':
                $this->modalTitle = 'Información de Valor Total de Refinanciaciones';
                break;
            case 'valor_interes':
                $this->modalTitle = 'Información de Valor de Refinanciaciones (Con Interés)';
                break;
            default:
                $this->modalTitle = 'Información de Refinanciaciones';
                break;
        }
    }

    public function loadRefinanciaciones(): void
    {
        $query = Refinanciamiento::query()->with(['prestamo.cliente']);

        if ($this->usuarioId !== null) {
            $query->whereHas('prestamo', function ($q) {
                $q->where('agente_asignado', $this->usuarioId);
            });
        }

        // En "cantidad" mostramos autorizadas + pendientes
        if ($this->modalType === 'cantidad') {
            $query->whereIn('estado', ['autorizado', 'pendiente']);
        }
        // En "valor_total" y "valor_interes" sólo autorizadas
        else {
            $query->where('estado', 'autorizado');
        }

        // Filtro de fecha y hora (igual para todos los modos)
        if ($this->fechaInicio && $this->fechaFin) {
            $query->whereBetween('created_at', [
                Carbon::parse($this->fechaInicio),
                Carbon::parse($this->fechaFin),
            ]);
        }

        $this->refinanciaciones = $query->get();
    }


    public function autorizarRefinanciamiento(int $refinanciamientoId): void
    {
        $refinanciamiento = Refinanciamiento::find($refinanciamientoId);

        if (!$refinanciamiento) {
            Notification::make()
                ->title('Error')
                ->body('Refinanciación no encontrada.')
                ->danger()
                ->send();
            return;
        }

        if ($refinanciamiento->estado === 'pendiente') {
            $refinanciamiento->estado = 'autorizado';
            $refinanciamiento->save(); // Esto disparará el evento 'saved' en el modelo Refinanciamiento

            Notification::make()
                ->title('Refinanciación Autorizada')
                ->body('La refinanciación ha sido autorizada correctamente.')
                ->success()
                ->send();

            $this->loadRefinanciaciones(); // Recargar la lista en el modal
            $this->dispatch('statsUpdated'); // Avisar al componente padre para que recalcule estadísticas
        } else {
            Notification::make()
                ->title('Advertencia')
                ->body('Esta refinanciación no está en estado pendiente.')
                ->warning()
                ->send();
        }
    }
    /**
     * Elimina una refinanciación específica de la base de datos.
     * Muestra notificaciones de éxito o error.
     *
     * @param int $refinanciamientoId El ID del refinanciamiento a eliminar.
     */
    public function deleteRefinanciamiento(int $refinanciamientoId): void
    {
        try {
            // Buscamos el refinanciamiento para asegurar que pertenece al usuario actual (si se especificó)
            $refinanciamiento = Refinanciamiento::find($refinanciamientoId);

            if (!$refinanciamiento) {
                Notification::make()
                    ->title('Error al eliminar')
                    ->body('La refinanciación no fue encontrada.')
                    ->danger()
                    ->send();
                return;
            }

            // Opcional: Si quieres asegurar que solo el usuario que abrió el modal puede eliminar
            // if ($this->usuarioId !== null && $refinanciamiento->prestamo->agente_asignado != $this->usuarioId) {
            //      Notification::make()
            //          ->title('Permiso denegado')
            //          ->body('No tienes permiso para eliminar esta refinanciación.')
            //          ->danger()
            //          ->send();
            //      return;
            // }

            $refinanciamiento->delete(); // Elimina el registro
            $this->loadRefinanciaciones(); // Recarga las refinanciaciones para actualizar la tabla en el modal

            Notification::make()
                ->title('Refinanciación eliminada')
                ->body('La refinanciación ha sido eliminada exitosamente.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Ocurrió un error al intentar eliminar la refinanciación: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * Redirige a la página de edición de un préstamo específico en Filament.
     *
     * @param int $prestamoId El ID del préstamo asociado a la refinanciación.
     */
    public function goToPrestamoEdit(int $prestamoId): void
    {
        $url = PrestamoResource::getUrl('edit', ['record' => $prestamoId]);
        $this->closeModal();
        $this->redirect($url);
    }

    public function render()
    {
        return view('livewire.refinanciaciones.refinanciaciones-modal');
    }
}