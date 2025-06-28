<?php

namespace App\Livewire\Comisiones;

use Livewire\Component;
use App\Models\Prestamo;
use App\Models\Refinanciamiento;
use App\Models\User;
use App\Models\Cliente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
// use App\Filament\Resources\PrestamoResource; // Este 'use' no es necesario si no se usa directamente
use Filament\Notifications\Notification;

class ComisionesRegistradasModal extends Component
{
    public bool $showModal = false;
    public ?int $agenteAsignadoId = null;
    public ?string $fechaInicio = null;
    public ?string $fechaFin = null;
    public Collection $comisiones;

    protected $listeners = [
        'abrirModalComisionesRegistradas' => 'abrirModal',
    ];

    public function mount(): void
    {
        $this->comisiones = collect();
    }

    public function abrirModal(?int $agenteAsignadoId, ?string $fechaInicio, ?string $fechaFin): void
    {
        $this->agenteAsignadoId = $agenteAsignadoId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->cargarComisiones();
        $this->showModal = true;
    }

    public function cerrarModal(): void
    {
        $this->showModal = false;
        $this->reset(['agenteAsignadoId', 'fechaInicio', 'fechaFin']);
        $this->comisiones = collect();
    }

    protected function cargarComisiones(): void
    {
        if (!$this->agenteAsignadoId) {
            $this->comisiones = collect();
            return;
        }

        $queryFechaInicio = $this->fechaInicio ? Carbon::parse($this->fechaInicio) : null; // Usar la fecha y hora exactas
        $queryFechaFin = $this->fechaFin ? Carbon::parse($this->fechaFin) : null;     // Usar la fecha y hora exactas

        // Comisiones de Préstamos
        // Solo cargar comisiones con un valor > 0 y que no estén borradas.
        $prestamoComisiones = Prestamo::where('agente_asignado', $this->agenteAsignadoId)
            ->whereNotNull('comicion')
            ->where('comicion', '>', 0) // <-- Asegura que solo se muestren comisiones con valor
            ->where('comicion_borrada', false) // <-- Solo comisiones no borradas
            ->when($queryFechaInicio && $queryFechaFin, function ($query) use ($queryFechaInicio, $queryFechaFin) {
                $query->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            })
            ->get(['id', 'cliente_id', 'comicion', 'created_at'])
            ->map(function ($prestamo) {
                return [
                    'id' => $prestamo->id, // El ID del préstamo
                    'fecha' => Carbon::parse($prestamo->created_at), // Mantener como objeto Carbon para ordenar
                    'cliente_nombre' => $prestamo->cliente->nombre ?? 'Desconocido',
                    'monto' => $prestamo->comicion,
                    'origen' => 'Préstamo', // Indica que viene de un préstamo
                    'prestamo_id' => $prestamo->id,
                ];
            });

        // Comisiones de Refinanciamientos
        // Solo cargar comisiones con un valor > 0 y que no estén borradas.
        $refinanciamientoComisiones = Refinanciamiento::whereHas('prestamo', function ($q) {
                $q->where('agente_asignado', $this->agenteAsignadoId);
            })
            ->where('estado', 'autorizado')
            ->whereNotNull('comicion')
            ->where('comicion', '>', 0) // <-- Asegura que solo se muestren comisiones con valor
            ->where('comicion_borrada', false) // <-- Solo comisiones no borradas
            ->when($queryFechaInicio && $queryFechaFin, function ($query) use ($queryFechaInicio, $queryFechaFin) {
                $query->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            })
            ->get(['id', 'prestamo_id', 'comicion', 'created_at'])
            ->map(function ($refinanciamiento) {
                return [
                    'id' => $refinanciamiento->id, // El ID del refinanciamiento
                    'fecha' => Carbon::parse($refinanciamiento->created_at), // Mantener como objeto Carbon
                    'cliente_nombre' => $refinanciamiento->prestamo->cliente->nombre ?? 'Desconocido',
                    'monto' => $refinanciamiento->comicion,
                    'origen' => 'Refinanciamiento', // Indica que viene de un refinanciamiento
                    'prestamo_id' => $refinanciamiento->prestamo_id, // Referencia al préstamo original
                ];
            });

        // Combina ambas colecciones y ordénalas por fecha
        // Ahora 'fecha' es un objeto Carbon, por lo que sortByDesc debería funcionar directamente.
        // Si aún da problemas, el callback explícito es más seguro.
        $this->comisiones = $prestamoComisiones->merge($refinanciamientoComisiones)->sortByDesc(function ($comision) {
            return $comision['fecha']; // Acceder al objeto Carbon directamente
        });
    }

    /**
     * "Elimina" una comisión específica, estableciendo comicion a 0, comicion_borrada a true
     * y comicion_cobrada a true.
     *
     * @param int $id El ID del registro de comisión (préstamo o refinanciamiento)
     * @param string $origen El origen de la comisión ('Préstamo' o 'Refinanciamiento')
     */
    public function deleteCommission(int $id, string $origen): void
    {
        try {
            if ($origen === 'Préstamo') {
                $record = Prestamo::where('id', $id)
                                    ->where('agente_asignado', $this->agenteAsignadoId)
                                    ->first();
                if ($record) {
                    $record->comicion = 0; // <-- Establece la comisión a 0
                    $record->comicion_borrada = true; // <-- Marca como borrada
                    $record->comicion_cobrada = true; // <-- Marca como cobrada
                    $record->saveQuietly(); // Usar saveQuietly
                    Notification::make()
                        ->title('Comisión de Préstamo anulada')
                        ->body('La comisión del préstamo ha sido anulada y marcada como borrada.')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Error al anular')
                        ->body('No se encontró el préstamo o no tienes permiso para modificarlo.')
                        ->danger()
                        ->send();
                }
            } elseif ($origen === 'Refinanciamiento') {
                $record = Refinanciamiento::where('id', $id)
                                            ->whereHas('prestamo', fn($q) => $q->where('agente_asignado', $this->agenteAsignadoId))
                                            ->first();
                if ($record) {
                    $record->comicion = 0; // <-- Establece la comisión a 0
                    $record->comicion_borrada = true; // <-- Marca como borrada
                    $record->comicion_cobrada = true; // <-- Marca como cobrada
                    $record->saveQuietly(); // Usar saveQuietly
                    Notification::make()
                        ->title('Comisión de Refinanciamiento anulada')
                        ->body('La comisión del refinanciamiento ha sido anulada y marcada como borrada.')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Error al anular')
                        ->body('No se encontró el refinanciamiento o no tienes permiso para modificarlo.')
                        ->danger()
                        ->send();
                }
            } else {
                Notification::make()
                    ->title('Error')
                    ->body('Origen de comisión desconocido.')
                    ->danger()
                    ->send();
            }

            $this->cargarComisiones(); // Recarga las comisiones para actualizar la tabla
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error')
                ->body('Ocurrió un error al intentar anular la comisión: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render()
    {
        $agente = $this->agenteAsignadoId ? User::find($this->agenteAsignadoId) : null;

        return view('livewire.comisiones.comisiones-registradas-modal', [
            'agente' => $agente,
        ]);
    }
}