<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;
use App\Models\Prestamo;
use Illuminate\Support\Collection; // Importar Collection

class UserHistorialModal extends Component
{
    public $user;
    public Collection $prestamosRegistrados;
    public float $totalDeudaActual = 0;

    // Propiedad para el mensaje de estado (opcional, si lo necesitas en el modal)
    public string $estadoMensaje = '';

    protected $listeners = ['loadUserHistorialData' => 'loadData'];



    public function mount( User $user)
    {

        $this->user = $user->loadMissing("prestamos.cliente"); // Cargar los préstamos y sus clientes relacionados
        $this->loadData(); // Carga inicial de datos
    }

    public function loadData()
    {
        // Carga solo los préstamos donde este usuario es el 'registrado_id'
        $this->prestamosRegistrados = $this->user->prestamos // Cargar la relación del cliente para el nombre
                                            ->sortByDesc('created_at'); // Ordenar por fecha de creación del préstamo

        $this->totalDeudaActual = $this->prestamosRegistrados->sum('deuda_actual');
    }

    public function render()
    {
        return view('livewire.user-historial-modal');
    }
}