<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Prestamo;
use App\Models\User;
use App\Models\HistorialMovimiento;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class LoanCharts extends Component
{
    public $usuarioSeleccionadoId;
    public $rolSeleccionado;

    public $anioSeleccionado;
    public $mesSeleccionado;

    public array $pieChartData = [
        'labels' => [],
        'data'   => [],
        'colors' => [],
    ];

    public array $lineChartData = [
        'labels' => [],
        'data'   => [],
    ];

    protected $listeners = [
        'updateChartData'     => 'refreshChartData',
        'updateLineChartData' => 'refreshLineChartData',
    ];

    public function mount($usuarioSeleccionadoId = null, $rolSeleccionado = null, $anioSeleccionado = null, $mesSeleccionado = null): void
    {
        $this->usuarioSeleccionadoId = $usuarioSeleccionadoId;
        $this->rolSeleccionado = $rolSeleccionado;

        $this->anioSeleccionado = $anioSeleccionado ?? date('Y');
        $this->mesSeleccionado  = $mesSeleccionado ?? date('m');

        // Llamar al método unificado al montar
        $this->fetchAllChartsData();

        // --- INICIO DEPURACIÓN SERVER-SIDE: mount() ---
        error_log('LoanCharts: mount() - usuarioSeleccionadoId: ' . ($this->usuarioSeleccionadoId ?? 'null') . ', rolSeleccionado: ' . ($this->rolSeleccionado ?? 'null'));
        error_log('LoanCharts: mount() - pieChartData inicial: ' . json_encode($this->pieChartData));
        error_log('LoanCharts: mount() - lineChartData inicial: ' . json_encode($this->lineChartData));
        // --- FIN DEPURACIÓN SERVER-SIDE: mount() ---
    }

    /**
     * Método público llamado por wire:poll y por cambios de props.
     * Se encarga de actualizar los datos de todos los gráficos.
     */
    public function fetchAllChartsData(): void
    {
        $this->computeChartData();
        $this->computeLineChartData();
    }

    // Hook del ciclo de vida para reaccionar a cambios en las propiedades pasadas desde el padre
    public function updated($propertyName): void
    {
        // Si el ID del usuario seleccionado o el rol seleccionado cambian,
        // recalcula los datos de ambos gráficos inmediatamente.
        if (in_array($propertyName, ['usuarioSeleccionadoId', 'rolSeleccionado', 'anioSeleccionado', 'mesSeleccionado'])) {
            // Añadimos anioSeleccionado y mesSeleccionado aquí para que los cambios en los filtros también disparen la actualización
            $this->fetchAllChartsData();

            // --- INICIO DEPURACIÓN SERVER-SIDE: updated() ---
            error_log('LoanCharts: updated() - Propiedad actualizada: ' . $propertyName);
            error_log('LoanCharts: updated() - usuarioSeleccionadoId: ' . ($this->usuarioSeleccionadoId ?? 'null') . ', rolSeleccionado: ' . ($this->rolSeleccionado ?? 'null'));
            // --- FIN DEPURACIÓN SERVER-SIDE: updated() ---
        }
    }

    // ─────── Pie Chart ───────

    public function refreshChartData($usuarioSeleccionadoId = null, $rolSeleccionado = null): void
    {
        if ($usuarioSeleccionadoId !== null) {
            $this->usuarioSeleccionadoId = $usuarioSeleccionadoId;
        }
        if ($rolSeleccionado !== null) {
            $this->rolSeleccionado = $rolSeleccionado;
        }
        $this->computeChartData();

        // --- INICIO DEPURACIÓN SERVER-SIDE: refreshChartData() ---
        error_log('LoanCharts: refreshChartData() - usuarioSeleccionadoId: ' . ($this->usuarioSeleccionadoId ?? 'null') . ', rolSeleccionado: ' . ($this->rolSeleccionado ?? 'null'));
        // --- FIN DEPURACIÓN SERVER-SIDE: refreshChartData() ---
    }

    public function computeChartData(): void
    {


        $this->resetChartData();

        // --- INICIO DEPURACIÓN SERVER-SIDE: computeChartData() ---
        error_log('LoanCharts: computeChartData() - Inicio del cálculo de datos del pie chart');
        error_log('LoanCharts: computeChartData() - usuarioSeleccionadoId: ' . ($this->usuarioSeleccionadoId ?? 'null') . ', rolSeleccionado: ' . ($this->rolSeleccionado ?? 'null'));
        // --- FIN DEPURACIÓN SERVER-SIDE: computeChartData() ---

        if (!$this->usuarioSeleccionadoId || !$this->rolSeleccionado) {
            // --- INICIO DEPURACIÓN SERVER-SIDE: computeChartData() - No usuario o rol ---
            error_log('LoanCharts: computeChartData() - No hay usuario o rol seleccionado. Datos del pie chart vacíos.');
            // --- FIN DEPURACIÓN SERVER-SIDE: computeChartData() - No usuario o rol ---
            $this->dispatch('chartDataUpdated', pieChartData: $this->pieChartData);
            return;
        }

        $usuario = User::find($this->usuarioSeleccionadoId);
        if (!$usuario) {
            // --- INICIO DEPURACIÓN SERVER-SIDE: computeChartData() - No usuario encontrado ---
            error_log('LoanCharts: computeChartData() - Usuario no encontrado. Datos del pie chart vacíos.');
            // --- FIN DEPURACIÓN SERVER-SIDE: computeChartData() - No usuario encontrado ---
            $this->dispatch('chartDataUpdated', pieChartData: $this->pieChartData);
            return;
        }

        $baseQuery = Prestamo::whereIn('estado', ['autorizado', 'activo']);

        $prestamos = $this->rolSeleccionado === 'Oficina'
            ? $baseQuery->clone()->whereHas('cliente', fn($q) => $q->where('oficina_id', $usuario->id))->get()
            : $baseQuery->clone()->where('agente_asignado', $usuario->id)->get();

        // --- INICIO DEPURACIÓN ADICIONAL ---
        error_log('LoanCharts: computeChartData() - Number of loans fetched before filtering by next_payment: ' . $prestamos->count());
        if ($prestamos->count() > 0) {
            $debugLoans = [];
            foreach ($prestamos->take(5) as $p) { // Log details for a few loans
                $isCarbon = $p->next_payment instanceof \Carbon\Carbon;
                $debugLoans[] = [
                    'id' => $p->id,
                    'estado' => $p->estado,
                    'next_payment_raw' => $p->getAttributes()['next_payment'] ?? null, // Valor crudo
                    'next_payment_casted' => $p->next_payment ? ($isCarbon ? $p->next_payment->toDateTimeString() : strval($p->next_payment)) : null,
                    'next_payment_is_carbon' => $isCarbon,
                    'is_past' => $p->next_payment && $isCarbon ? $p->next_payment->isPast() : null,
                    'is_today' => $p->next_payment && $isCarbon ? $p->next_payment->isToday() : null,
                    'gte_today' => $p->next_payment && $isCarbon ? $p->next_payment->gte(Carbon::today()) : null,
                ];
            }
            error_log('LoanCharts: computeChartData() - Sample loans details: ' . json_encode($debugLoans, JSON_PRETTY_PRINT));
        }
        // --- FIN DEPURACIÓN ADICIONAL ---

        $vencidos = $prestamos->filter(fn($p) => $p->next_payment && $p->next_payment->isPast() && !$p->next_payment->isToday())->count();
        $alDia = $prestamos->filter(fn($p) => $p->next_payment && $p->next_payment->gte(Carbon::today()))->count();

        $this->pieChartData = [
            'labels' => ['Préstamos Vencidos', 'Préstamos al Día'],
            'data'   => [$vencidos, $alDia],
            'colors' => ['#F44336', '#4CAF50'],
        ];

        // --- INICIO DEPURACIÓN SERVER-SIDE: computeChartData() - Datos calculados ---
        error_log('LoanCharts: computeChartData() - Datos calculados del pie chart: ' . json_encode($this->pieChartData));
        // dd($this->pieChartData); // Descomentar para ver los datos en el navegador
        // --- FIN DEPURACIÓN SERVER-SIDE: computeChartData() - Datos calculados ---

        $this->dispatch('chartDataUpdated', pieChartData: $this->pieChartData);
    }

    protected function resetChartData(): void
    {
        $this->pieChartData = [
            'labels' => [],
            'data'   => [],
            'colors' => [],
        ];
    }

    // ─────── Line Chart ───────

    public function refreshLineChartData($usuarioId = null, $anio = null, $mes = null): void
    {
        // Asignar solo si los valores no son null para evitar sobrescribir con nada
        if ($usuarioId !== null) {
            $this->usuarioSeleccionadoId = $usuarioId;
        }
        if ($anio !== null) {
            $this->anioSeleccionado = $anio;
        }
        if ($mes !== null) {
            $this->mesSeleccionado = $mes;
        }
        $this->computeLineChartData();
    }

    public function computeLineChartData(): void
    {
        $this->resetLineChartData();

        // --- INICIO DEPURACIÓN SERVER-SIDE: computeLineChartData() ---
        error_log('LoanCharts: computeLineChartData() - usuarioSeleccionadoId: ' . ($this->usuarioSeleccionadoId ?? 'null') . ', anioSeleccionado: ' . ($this->anioSeleccionado ?? 'null') . ', mesSeleccionado: ' . ($this->mesSeleccionado ?? 'null'));
        // --- FIN DEPURACIÓN SERVER-SIDE: computeLineChartData() ---

        if (!$this->usuarioSeleccionadoId || !$this->anioSeleccionado || !$this->mesSeleccionado) {
            // --- INICIO DEPURACIÓN SERVER-SIDE ---
            error_log('LoanCharts: computeLineChartData() - Faltan parámetros, despachando datos vacíos para lineChartData.');
            // --- FIN DEPURACIÓN SERVER-SIDE ---
            $this->dispatch('lineChartDataUpdated', lineChartData: $this->lineChartData);
            return;
        }

        $usuario = User::find($this->usuarioSeleccionadoId);
        if (!$usuario) {
            // --- INICIO DEPURACIÓN SERVER-SIDE ---
            error_log('LoanCharts: computeLineChartData() - Usuario no encontrado, despachando datos vacíos para lineChartData.');
            // --- FIN DEPURACIÓN SERVER-SIDE ---
            $this->dispatch('lineChartDataUpdated', lineChartData: $this->lineChartData);
            return;
        }

        $inicioMes = Carbon::create($this->anioSeleccionado, $this->mesSeleccionado, 1)->startOfDay();
        $finMes    = $inicioMes->copy()->endOfMonth();
        $periodo   = CarbonPeriod::create($inicioMes, $finMes);

        // Ajustamos la consulta para que tome registros de movimientos que *podrían* afectar el monto inicial del mes actual
        // Esto significa buscar el último registro antes del inicio del mes, si existe.
        $registros = HistorialMovimiento::where('user_id', $usuario->id)
            ->where('es_edicion', true)
            ->where('tabla_origen', 'dinero_bases')
            // Traemos los registros desde el día 1 del mes en curso hasta el día actual si el mes actual no ha terminado,
            // o hasta el final del mes si el mes ya pasó.
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->orderBy('fecha')
            ->get();


        $montosPorDia = [];
        foreach ($registros as $registro) {
            $fechaKey = $registro->fecha->format('Y-m-d');
            $cambio = json_decode($registro->cambio_hacia, true);
            $monto = is_array($cambio) && isset($cambio['monto']) ? floatval($cambio['monto']) : 0;
            $montosPorDia[$fechaKey] = $monto; // Guarda el último monto registrado para ese día
        }

        $labels = [];
        $data = [];
        $ultimoMontoConocido = 0;

        // Encontrar el último monto antes del inicio del período actual
        $ultimoRegistroAntesDelPeriodo = HistorialMovimiento::where('user_id', $usuario->id)
            ->where('es_edicion', true)
            ->where('tabla_origen', 'dinero_bases')
            ->where('fecha', '<', $inicioMes) // Registros anteriores al mes actual
            ->orderBy('fecha', 'desc')
            ->first();

        if ($ultimoRegistroAntesDelPeriodo) {
            $cambio = json_decode($ultimoRegistroAntesDelPeriodo->cambio_hacia, true);
            $ultimoMontoConocido = is_array($cambio) && isset($cambio['monto']) ? floatval($cambio['monto']) : 0;
            // --- INICIO DEPURACIÓN SERVER-SIDE ---
            error_log('computeLineChartData: Último monto conocido antes del mes: ' . $ultimoMontoConocido);
            // --- FIN DEPURACIÓN SERVER-SIDE ---
        } else {
            // --- INICIO DEPURACIÓN SERVER-SIDE ---
            error_log('computeLineChartData: No se encontró un monto anterior al inicio del mes.');
            // --- FIN DEPURACIÓN SERVER-SIDE ---
        }


        foreach ($periodo as $dia) {
            $key = $dia->format('Y-m-d');
            $labels[] = $dia->format('d'); // Formato de día '01', '02', etc.

            if ($dia->isFuture()) {
                // No incluir datos para días futuros
                $data[] = null;
            } elseif (isset($montosPorDia[$key])) {
                $ultimoMontoConocido = $montosPorDia[$key];
                $data[] = $ultimoMontoConocido;
            } else {
                // Si no hay un monto explícito para el día actual, usa el último monto conocido
                // Esto es crucial para rellenar los días sin movimientos con el valor persistente
                $data[] = $ultimoMontoConocido;
            }
        }
        // --- INICIO DEPURACIÓN SERVER-SIDE: Depuración Adicional para la línea de tiempo ---
        error_log('computeLineChartData: Labels finales: ' . json_encode($labels));
        error_log('computeLineChartData: Data final: ' . json_encode($data));
        // --- FIN DEPURACIÓN SERVER-SIDE ---


        $this->lineChartData = [
            'labels' => $labels,
            'data'   => $data,
        ];

        // --- INICIO DEPURACIÓN SERVER-SIDE ---
        error_log('computeLineChartData: Datos calculados para lineChartData: ' . json_encode($this->lineChartData));
        // dd($this->lineChartData); // Descomentar para ver los datos en el navegador
        // --- FIN DEPURACIÓN SERVER-SIDE ---

        $this->dispatch('lineChartDataUpdated', lineChartData: $this->lineChartData);
    }

    protected function resetLineChartData(): void
    {
        $this->lineChartData = [
            'labels' => [],
            'data'   => [],
        ];
    }

    public function render()
    {
        return view('livewire.loan-charts');
    }
}