<?php

namespace App\Livewire\Comisiones;

use Livewire\Component;
use App\Models\Prestamo;
use App\Models\Refinanciamiento;
use App\Models\User;
use App\Models\Cliente;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        $queryFechaInicio = $this->fechaInicio ? Carbon::parse($this->fechaInicio) : null;
        $queryFechaFin = $this->fechaFin ? Carbon::parse($this->fechaFin) : null;

        // --- INICIO DE LA CORRECCIÓN DE LÓGICA DE FECHAS ---
        // Se define la misma lógica de fechas que usan los otros servicios para mantener la consistencia.
        $dateFilterLogic = function ($query) use ($queryFechaInicio, $queryFechaFin) {
            $query->where(function ($subQuery) use ($queryFechaInicio, $queryFechaFin) {
                // Opción 1: Si no tiene fecha de autorización, usa la de creación
                $subQuery->whereNull('authorized_at')
                         ->whereBetween('created_at', [$queryFechaInicio, $queryFechaFin]);
            })->orWhere(function ($subQuery) use ($queryFechaInicio, $queryFechaFin) {
                // Opción 2: Si SÍ tiene fecha de autorización, usa esa fecha
                $subQuery->whereNotNull('authorized_at')
                         ->whereBetween('authorized_at', [$queryFechaInicio, $queryFechaFin]);
            });
        };
        // --- FIN DE LA CORRECCIÓN DE LÓGICA DE FECHAS ---

        // 1. Comisiones de Préstamos
        $prestamosQuery = Prestamo::where('registrado_id', $this->agenteAsignadoId)
            ->whereIn('estado', ['autorizado', 'activo', 'finalizado', 'desactivado'])
            ->whereNotNull('comicion')
            ->where('comicion', '>', 0)
            ->where('comicion_borrada', false)
            ->with('cliente:id,nombre');
            
        // Aplicar el filtro de fechas si se proporcionan
        if ($queryFechaInicio && $queryFechaFin) {
            $prestamosQuery->where($dateFilterLogic); // <-- Usando la lógica de fecha correcta
        }
        $prestamosConComision = $prestamosQuery->get();

        $prestamoComisiones = $prestamosConComision
            ->filter(fn($prestamo) => $prestamo->cliente)
            ->map(function ($prestamo) {
                return [
                    'id' => $prestamo->id,
                    'fecha' => Carbon::parse($prestamo->authorized_at ?? $prestamo->created_at), // Usar la fecha relevante
                    'cliente_nombre' => $prestamo->cliente->nombre,
                    'monto' => $prestamo->comicion,
                    'origen' => 'Préstamo',
                    'prestamo_id' => $prestamo->id,
                ];
            });

        // 2. Comisiones de Refinanciamientos
        $refinanciamientosQuery = Refinanciamiento::whereHas('prestamo', function ($q) {
                $q->where('registrado_id', $this->agenteAsignadoId)
                  ->whereIn('estado', ['autorizado', 'activo', 'finalizado', 'desactivado']);
            })
            ->where('estado', 'autorizado')
            ->whereNotNull('comicion')
            ->where('comicion', '>', 0)
            ->where('comicion_borrada', false)
            ->with('prestamo.cliente:id,nombre');

        // Aplicar el filtro de fechas si se proporcionan
        if ($queryFechaInicio && $queryFechaFin) {
            $refinanciamientosQuery->where($dateFilterLogic); // <-- Usando la lógica de fecha correcta
        }
        $refinanciamientosConComision = $refinanciamientosQuery->get();

        $refinanciamientoComisiones = $refinanciamientosConComision
            ->filter(fn($r) => $r->prestamo && $r->prestamo->cliente)
            ->map(function ($refinanciamiento) {
                return [
                    'id' => $refinanciamiento->id,
                    'fecha' => Carbon::parse($refinanciamiento->authorized_at ?? $refinanciamiento->created_at), // Usar la fecha relevante
                    'cliente_nombre' => $refinanciamiento->prestamo->cliente->nombre,
                    'monto' => $refinanciamiento->comicion,
                    'origen' => 'Refinanciamiento',
                    'prestamo_id' => $refinanciamiento->prestamo_id,
                ];
            });

        // Combinar y ordenar
        $arrayPrestamos = $prestamoComisiones->toArray();
        $arrayRefinanciamientos = $refinanciamientoComisiones->toArray();
        $comisionesFusionadas = array_merge($arrayPrestamos, $arrayRefinanciamientos);

        $this->comisiones = collect($comisionesFusionadas)
            ->sortByDesc(fn ($comision) => Carbon::parse($comision['fecha']))
            ->values();
    }
    
    // El método deleteCommission no necesita cambios, ya estaba correcto.
    public function deleteCommission(int $id, string $origen): void
    {
        // ... (código sin cambios)
    }

    public function render()
    {
        $agente = $this->agenteAsignadoId ? User::find($this->agenteAsignadoId) : null;

        return view('livewire.comisiones.comisiones-registradas-modal', [
            'agente' => $agente,
        ]);
    }
}