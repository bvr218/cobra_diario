<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Cliente;
use Carbon\Carbon;

class ClienteHistorialModal extends Component
{
    public Cliente $cliente;
    public $prestamos;
    public $abonos;
    public $refinanciaciones;

    /**
     * Carga y recarga todos los datos relacionados.
     */
    private function loadData(): void
    {
        $clienteModel = Cliente::with([
            'prestamos' => function ($query) {
                $query->orderBy('created_at', 'desc')
                      ->with([
                          'abonos' => function ($q_abonos) {
                              $q_abonos->orderBy('fecha_abono', 'desc')
                                       ->with('registradoPor');
                          },
                          'refinanciamientos' => function ($q_ref) {
                              $q_ref->orderBy('created_at', 'desc');
                          },
                          'registrado',
                      ]);
            }
        ])->find($this->cliente->id);

        if (!$clienteModel) {
            $this->prestamos = collect();
            $this->abonos = collect();
            $this->refinanciaciones = collect();
            return;
        }

        $this->cliente = $clienteModel;
        $this->prestamos = $this->cliente->prestamos;

        $this->abonos = $this->prestamos
            ->flatMap(fn($p) => $p->abonos ?? collect())
            ->sortByDesc(fn($a) => Carbon::parse($a->fecha_abono));

        $this->refinanciaciones = $this->prestamos
            ->flatMap(fn($p) => $p->refinanciamientos ?? collect())
            ->sortByDesc(fn($r) => Carbon::parse($r->created_at));
    }

    public function mount(Cliente $cliente)
    {
        $this->cliente = $cliente;
        $this->loadData();
    }

    public function updatedCliente(Cliente $newCliente): void
    {
        $this->loadData();
    }

    public function render()
    {
        return view('livewire.cliente-historial-modal');
    }
}
