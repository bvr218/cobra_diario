<?php

namespace App\Livewire\VistaLiquidacion;

use Livewire\Component;
use App\Models\DineroBase;

class VistaAjustesModal extends Component
{
    public bool $showModal = false;
    public array $lista = [];
    public string $titulo = '';

    // Propiedades para una posible futura exportación (opcional)
    public ?int $selectedAgentId = null;
    public ?int $selectedYear = null;
    public ?int $selectedMonth = null;
    public string $tipoLista = 'ajustes_dinero';

    protected $listeners = ['abrirVistaAjustesModal' => 'abrirModal'];

    public function abrirModal(array $lista, string $titulo, ?int $agentId = null, ?int $year = null, ?int $month = null): void
    {
        $this->lista = $lista;
        $this->titulo = $titulo;
        $this->selectedAgentId = $agentId;
        $this->selectedYear = $year;
        $this->selectedMonth = $month;
        $this->showModal = true;
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        $this->reset(['lista', 'titulo', 'selectedAgentId', 'selectedYear', 'selectedMonth']);
    }

    public function render()
    {
        $columnLabels = DineroBase::getColumnLabels();

        return view('livewire.vista-liquidacion.vista-ajustes-modal', [
            'columnLabels' => $columnLabels
        ]);
    }
}