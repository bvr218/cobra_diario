<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;
use App\Models\AjusteDinero;
use Illuminate\Support\Carbon;
use App\Models\DineroBase;

class AjustesDineroModal extends Component
{
    public bool $showModal = false;
    public $ajustes = [];
    public ?int $userId = null;
    public ?string $fechaInicio = null;
    public ?string $fechaFin = null;
    public string $modalTitle = 'Detalle de Ajustes de Dinero';

    protected $listeners = ['abrirModalAjustesDinero' => 'abrirModal'];

    public function abrirModal(?int $userId = null, ?string $fechaInicio = null, ?string $fechaFin = null): void
    {
        $this->userId = $userId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;

        if ($this->userId) {
            $query = AjusteDinero::with('ajustadoPor:id,name')
                ->where('user_id', $this->userId);

            if ($this->fechaInicio && $this->fechaFin) {
                $query->whereBetween('created_at', [
                    Carbon::parse($this->fechaInicio),
                    Carbon::parse($this->fechaFin)
                ]);
            }
            
            $this->ajustes = $query->orderBy('created_at', 'desc')->get();
            $this->showModal = true;
        }
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        $this->reset(['ajustes', 'userId', 'fechaInicio', 'fechaFin']);
    }

    public function render()
    {
        $columnLabels = DineroBase::getColumnLabels();

        return view('livewire.ajustes-dinero-modal', [
            'columnLabels' => $columnLabels
        ]);
    }
}