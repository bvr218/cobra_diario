<?php

namespace App\Livewire\Prestamos;

use Livewire\Component;
use App\Models\Prestamo;
use App\Models\User;
use App\Models\Cliente; // Asegúrate de que Cliente esté importado si lo necesitas
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon; // Asegúrate de usar Illuminate\Support\Carbon

class PrestamosEntregadosModal extends Component
{
    public bool $showModal = false;
    public $prestamos = [];
    public ?int $agenteAsignadoId = null;
    public ?string $fechaInicio = null;
    public ?string $fechaFin = null;
    public string $modalTitle = '';
    public string $valorCampo = 'valor_total_prestamo'; // <-- ¡Nueva propiedad clave! Define qué campo de Prestamo mostrar.

    // Escucha los tres eventos ahora
    protected $listeners = [
        'abrirModalPrestamosEntregados' => 'abrirModalPrestamosEntregados',
        'abrirModalTotalPrestado' => 'abrirModalTotalPrestado',
        'abrirModalTotalPrestadoConInteres' => 'abrirModalTotalPrestadoConInteres', // <-- Nuevo listener para el interés
    ];

    public function mount()
    {
        // Inicializa prestamos como una colección vacía
        $this->prestamos = [];
    }

    /**
     * Lógica común para cargar los préstamos según el tipo de solicitud.
     */
    protected function cargarPrestamos(): void
    {
        $query = Prestamo::query()
            ->with(['cliente', 'agenteAsignado'])
            ->where('agente_asignado', $this->agenteAsignadoId);

        if ($this->fechaInicio && $this->fechaFin) {
            $query->whereBetween('created_at', [
                Carbon::parse($this->fechaInicio), // Usar la fecha y hora exactas
                Carbon::parse($this->fechaFin)     // Usar la fecha y hora exactas
            ]);
        }

        // Modificación clave aquí:
        if (str_starts_with($this->modalTitle, 'Préstamos Entregados')) {
            // Para "Préstamos Entregados", mostrar activos, autorizados y pendientes
            $query->whereIn('estado', ['activo', 'autorizado', 'pendiente']);
        } else {
            // Para "Detalle de Total Prestado" y "Detalle de Total Prestado (Con Interés)", solo activos y autorizados
            $query->whereIn('estado', ['activo', 'autorizado']);
        }
        $query->orderBy('posicion_ruta', 'asc');

        $this->prestamos = $query->get(); // Obtiene la colección de préstamos
        $this->showModal = true;
    }

    /**
     * Abre el modal para "Préstamos Entregados".
     */
    public function abrirModalPrestamosEntregados(?int $agenteAsignadoId = null, ?string $fechaInicio = null, ?string $fechaFin = null): void
    {
        $this->agenteAsignadoId = $agenteAsignadoId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;

        $this->modalTitle = 'Préstamos Entregados';
        $this->valorCampo = 'valor_total_prestamo'; // Siempre mostrará el valor total

        if ($this->agenteAsignadoId) {
            $agente = User::find($this->agenteAsignadoId);
            if ($agente) {
                $this->modalTitle .= ' de ' . $agente->name;
            }
        }
        $this->cargarPrestamos();
    }

    /**
     * Abre el modal para "Detalle de Total Prestado".
     */
    public function abrirModalTotalPrestado(?int $agenteAsignadoId = null, ?string $fechaInicio = null, ?string $fechaFin = null): void
    {
        $this->agenteAsignadoId = $agenteAsignadoId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;

        $this->modalTitle = 'Detalle de Total Prestado';
        $this->valorCampo = 'valor_total_prestamo'; // Mostrará el valor total

        if ($this->agenteAsignadoId) {
            $agente = User::find($this->agenteAsignadoId);
            if ($agente) {
                $this->modalTitle .= ' de ' . $agente->name;
            }
        }
        $this->cargarPrestamos();
    }

    /**
     * Abre el modal para "Detalle de Total Prestado (Con Interés)".
     * Este método es nuevo y crucial para el cambio.
     */
    public function abrirModalTotalPrestadoConInteres(?int $agenteAsignadoId = null, ?string $fechaInicio = null, ?string $fechaFin = null): void
    {
        $this->agenteAsignadoId = $agenteAsignadoId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;

        $this->modalTitle = 'Detalle de Total Prestado (Con Interés)';
        $this->valorCampo = 'valor_prestado_con_interes'; // <-- ¡Cambia a esta propiedad!

        if ($this->agenteAsignadoId) {
            $agente = User::find($this->agenteAsignadoId);
            if ($agente) {
                $this->modalTitle .= ' de ' . $agente->name;
            }
        }
        $this->cargarPrestamos();
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        // Restablece todas las propiedades a sus valores iniciales
        $this->reset(['prestamos', 'agenteAsignadoId', 'fechaInicio', 'fechaFin', 'modalTitle', 'valorCampo']);
    }

    public function autorizarPrestamo(int $prestamoId): void
    {
        $prestamo = Prestamo::find($prestamoId);

        if (!$prestamo) {
            Notification::make()
                ->title('Error')
                ->body('Préstamo no encontrado.')
                ->danger()
                ->send();
            return;
        }

        // Opcional: Verificar si el préstamo pertenece al agente del modal, si $this->agenteAsignadoId está seteado
        if ($this->agenteAsignadoId !== null && $prestamo->agente_asignado != $this->agenteAsignadoId) {
            Notification::make()
                ->title('Error de Permiso')
                ->body('Este préstamo no pertenece al agente seleccionado en el modal.')
                ->danger()
                ->send();
            return;
        }

        if ($prestamo->estado === 'pendiente') {
            $prestamo->estado = 'autorizado';
            // $prestamo->fecha_autorizacion = Carbon::now(); // Opcional: si tienes este campo
            $prestamo->save();

            Notification::make()
                ->title('Préstamo Autorizado')
                ->body('El préstamo ha sido autorizado correctamente.')
                ->success()
                ->send();

            $this->cargarPrestamos(); // Recargar la lista en el modal
            $this->dispatch('statsUpdated'); // Avisar al componente padre para que recalcule estadísticas
        } else {
            Notification::make()
                ->title('Advertencia')
                ->body('Este préstamo no está en estado pendiente o ya ha sido procesado.')
                ->warning()
                ->send();
        }
    }
    // --- CAMBIO AQUÍ: Eliminado el tipo de retorno ---
    public function goToPrestamoEdit(int $prestamoId)
    {
        $this->cerrarModal(); // Cierra el modal antes de redirigir
        $url = \App\Filament\Resources\PrestamoResource::getUrl('edit', ['record' => $prestamoId]);
        return redirect()->to($url);
    }

    public function render()
    {
        return view('livewire.prestamos.prestamos-entregados-modal');
    }
}