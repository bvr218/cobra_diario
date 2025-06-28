<?php

namespace App\Livewire\Abonos;

use Livewire\Component;
use App\Models\Abono;
use App\Models\User;
use Illuminate\Support\Carbon;
use Filament\Notifications\Notification;

class AbonosRealizadosModal extends Component
{
    public bool $showModal = false;
    public $abonos = [];
    public ?int $usuarioAsignadoId = null;
    public ?string $fechaInicio = null;
    public ?string $fechaFin = null;
    public string $modalTitle = '';
    public string $campoValorAbono = 'monto_abono';
    public array $repeatedRows = []; // ¡NUEVA PROPIEDAD! Para almacenar los IDs de abonos que deben ser rojos

    protected $listeners = [
        'abrirModalRecaudosRealizados' => 'abrirModalRecaudosRealizados',
        'abrirModalDineroRecaudado' => 'abrirModalDineroRecaudado',
    ];

    public function mount()
    {
        $this->abonos = [];
        $this->repeatedRows = [];
    }

    protected function cargarAbonos(): void
    {
        $query = Abono::query()
            ->with(['prestamo.cliente', 'registradoPor'])
            ->where('registrado_por_id', $this->usuarioAsignadoId);

        if ($this->fechaInicio && $this->fechaFin) {
            $query->whereBetween('created_at', [
                Carbon::parse($this->fechaInicio), // Usar la fecha y hora exactas
                Carbon::parse($this->fechaFin)      // Usar la fecha y hora exactas
            ]);
        }

        $abonosCollection = $query->orderBy('created_at', 'desc')->get();

        // Lógica para identificar abonos repetidos en el mismo día por el mismo cliente
        $this->repeatedRows = $this->getRepeatedAbonoIds($abonosCollection);

        $this->abonos = $abonosCollection;
        $this->showModal = true;
    }

    /**
     * Identifica los IDs de los abonos que corresponden a clientes repetidos en el mismo día.
     *
     * @param \Illuminate\Support\Collection $abonos
     * @return array
     */
    private function getRepeatedAbonoIds(\Illuminate\Support\Collection $abonos): array
    {
        $repeatedIds = [];
        $dailyClientCounts = [];

        foreach ($abonos as $abono) {
            $date = Carbon::parse($abono->created_at)->format('Y-m-d');
            $clientId = $abono->prestamo->cliente->id ?? null; // Asegúrate de que el cliente exista

            if ($clientId) {
                $key = $date . '-' . $clientId;
                if (!isset($dailyClientCounts[$key])) {
                    $dailyClientCounts[$key] = [];
                }
                $dailyClientCounts[$key][] = $abono->id;
            }
        }

        foreach ($dailyClientCounts as $key => $ids) {
            if (count($ids) > 1) {
                // Si hay más de un abono para el mismo cliente en el mismo día,
                // agregamos todos los IDs de esos abonos a la lista de repetidos.
                $repeatedIds = array_merge($repeatedIds, $ids);
            }
        }

        return $repeatedIds;
    }


    public function abrirModalRecaudosRealizados(?int $usuarioId = null, ?string $fechaInicio = null, ?string $fechaFin = null): void
    {
        $this->usuarioAsignadoId = $usuarioId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;

        $this->modalTitle = 'Recaudos Realizados';
        $this->campoValorAbono = 'monto_abono';

        if ($this->usuarioAsignadoId) {
            $usuario = User::find($this->usuarioAsignadoId);
            if ($usuario) {
                $this->modalTitle .= ' por ' . $usuario->name;
            }
        }
        $this->cargarAbonos();
    }

    public function abrirModalDineroRecaudado(?int $usuarioId = null, ?string $fechaInicio = null, ?string $fechaFin = null): void
    {
        $this->usuarioAsignadoId = $usuarioId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;

        $this->modalTitle = 'Dinero Recaudado';
        $this->campoValorAbono = 'monto_abono';

        if ($this->usuarioAsignadoId) {
            $usuario = User::find($this->usuarioAsignadoId);
            if ($usuario) {
                $this->modalTitle .= ' por ' . $usuario->name;
            }
        }
        $this->cargarAbonos();
    }

    public function deleteAbono(int $abonoId): void
    {
        try {
            $abono = Abono::find($abonoId);

            if (!$abono) {
                Notification::make()
                    ->title('Error al eliminar')
                    ->body('El abono no fue encontrado.')
                    ->danger()
                    ->send();
                return;
            }

            $abono->delete();
            $this->cargarAbonos();

            Notification::make()
                ->title('Abono eliminado')
                ->body('El abono ha sido eliminado exitosamente.')
                ->success()
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Ocurrió un error al intentar eliminar el abono: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        $this->reset(['abonos', 'usuarioAsignadoId', 'fechaInicio', 'fechaFin', 'modalTitle', 'campoValorAbono', 'repeatedRows']); // Reiniciar repeatedRows
    }

    public function goToPrestamoEdit(int $prestamoId)
    {
        $this->cerrarModal();
        $url = \App\Filament\Resources\PrestamoResource::getUrl('edit', ['record' => $prestamoId]);
        return redirect()->to($url);
    }

    public function render()
    {
        return view('livewire.abonos.abonos-realizados-modal');
    }
}