<?php

namespace App\Livewire\VistaLiquidacion;

use Livewire\Component;

class VistaComisionesModal extends Component
{
    public bool $showModal = false;
    public array $lista = [];
    public string $titulo = '';
    
    // Propiedades para la exportación
    public ?int $selectedAgentId = null;
    public ?int $selectedYear = null;
    public ?int $selectedMonth = null;
    public string $tipoLista = 'comisiones'; // Identificador para la ruta

    protected $listeners = ['abrirVistaComisionesModal' => 'abrirModal'];

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
        return view('livewire.vista-liquidacion.vista-comisiones-modal');
    }
}