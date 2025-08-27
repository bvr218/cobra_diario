<?php

namespace App\Livewire\Prestamos;

use Livewire\Component;
use App\Models\Prestamo;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Collection;

class PrestamosFinalizadosModal extends Component
{
    public bool $showModal = false;
    public Collection $prestamos;
    public ?int $registradoId = null;
    public ?string $fechaInicio = null;
    public ?string $fechaFin = null;
    public string $modalTitle = 'Préstamos Finalizados';

    protected $listeners = [
        'abrirModalPrestamosFinalizados' => 'abrirModal',
    ];

    public function mount()
    {
        $this->prestamos = new Collection();
    }

    public function abrirModal(?int $registradoId = null, ?string $fechaInicio = null, ?string $fechaFin = null): void
    {
        $this->registradoId = $registradoId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;

        if ($this->registradoId) {
            $registrador = User::find($this->registradoId);
            if ($registrador) {
                $this->modalTitle = 'Préstamos Finalizados de ' . $registrador->name;
            }
            $this->cargarPrestamos();
        }
    }

    protected function cargarPrestamos(): void
    {
        $query = Prestamo::query()
            ->with([
                'cliente',
                // Carga los abonos ordenados para obtener fácilmente el último
                'abonos' => fn ($q) => $q->orderBy('created_at', 'desc')
            ])
            ->where('registrado_id', $this->registradoId)
            ->where('estado', 'finalizado');

        // ELIMINADO: Se quita el filtro de fecha para que el modal siempre muestre
        // todos los préstamos finalizados del usuario, en consistencia con el contador.

        $this->prestamos = $query->orderBy('updated_at', 'desc')->get();
        $this->showModal = true;
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        $this->reset(['prestamos', 'registradoId', 'fechaInicio', 'fechaFin', 'modalTitle']);
    }

    public function render()
    {
        return view('livewire.prestamos.prestamos-finalizados-modal');
    }
}