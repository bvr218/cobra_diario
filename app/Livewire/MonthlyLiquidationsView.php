<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;
use App\Models\RegistroLiquidacion;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Filament\Notifications\Notification;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use App\Services\StatsService;
use App\Services\LiquidationDataCollectionService;
use App\Services\ChartDataService;

class MonthlyLiquidationsView extends Component implements HasForms
{
    use InteractsWithForms;

    // Propiedades para la gestión de modales y selección
    public bool $showAgentModal = true;
    public bool $showYearModal = false;
    public bool $showMonthModal = false;
    public bool $showLiquidationDisplay = false;

    public ?User $selectedAgent = null;
    public ?int $selectedYear = null;
    public ?int $selectedMonth = null;

    public Collection $availableAgents;
    public Collection $availableYears;
    public Collection $availableMonths;

    public ?RegistroLiquidacion $selectedMonthlyLiquidation = null;

    // Propiedades para la liquidación en vivo
    public bool $isLiveMonthlyLiquidation = false;
    public array $liveLiquidationData = [];

    // Propiedades para los datos de los gráficos
    public array $dailyActivityChartData = [];
    public array $agentComparisonChartData = [];

    protected $listeners = [];

    public function mount(StatsService $statsService, LiquidationDataCollectionService $dataCollectionService): void
    {
        $this->availableAgents = collect();
        $this->availableYears = collect();
        $this->availableMonths = collect();
        $this->loadAllAgents();

        $today = Carbon::now();
        $currentUser = auth()->user();

        if ($currentUser && $currentUser->hasRole('agente') && $today->day === 1) {
            $previousMonth = $today->copy()->subMonth();
            $this->selectedAgent = $currentUser;
            $this->selectedYear = $previousMonth->year;
            $this->selectedMonth = $previousMonth->month;

            $this->selectedMonthlyLiquidation = RegistroLiquidacion::where('user_id', $this->selectedAgent->id)
                ->where('type', 'mensual')
                ->whereYear('desde', $this->selectedYear)
                ->whereMonth('desde', $this->selectedMonth)
                ->first();

            if ($this->selectedMonthlyLiquidation) {
                $this->isLiveMonthlyLiquidation = false;
                $this->showAgentModal = false;
                $this->showYearModal = false;
                $this->showMonthModal = false;
                $this->showLiquidationDisplay = true;

                $this->loadChartData();

                Notification::make()->title('Liquidación del mes anterior cargada')->body('Se ha cargado automáticamente la liquidación de ' . $previousMonth->locale('es')->monthName . ' ' . $previousMonth->year . '.')->info()->send();
                return;
            } else {
                Notification::make()->title('No encontrada')->body('No se encontró la liquidación mensual guardada para ' . $currentUser->name . ' del mes de ' . $previousMonth->locale('es')->monthName . ' ' . $previousMonth->year . '.')->warning()->send();
            }
        }
        
        $this->showAgentModal = true;
    }

    protected function loadAllAgents(): void
    {
        $this->availableAgents = User::role('agente')->orderBy('name')->get();

        if ($this->availableAgents->isEmpty()) {
            $this->showAgentModal = true;
            $this->showYearModal = false;
            $this->showMonthModal = false;
            $this->showLiquidationDisplay = false;
        }
    }

    public function openAgentSelectionModal(): void
    {
        $this->resetSelection();
    }

    public function selectAgent(int $agentId): void
    {
        $this->selectedAgent = User::find($agentId);
        if (!$this->selectedAgent) {
            Notification::make()->title('Error')->body('Agente no encontrado.')->danger()->send();
            return;
        }

        $this->showAgentModal = false;
        $this->showYearModal = true;
        $this->showMonthModal = false;
        $this->showLiquidationDisplay = false;
        $this->selectedYear = null;
        $this->selectedMonth = null;
        $this->selectedMonthlyLiquidation = null;
        $this->isLiveMonthlyLiquidation = false;
        $this->liveLiquidationData = [];
        $this->dailyActivityChartData = [];
        $this->agentComparisonChartData = [];

        $this->loadAvailableYears();
    }

    protected function loadAvailableYears(): void
    {
        if (!$this->selectedAgent) {
            $this->availableYears = collect();
            return;
        }

        $savedYears = RegistroLiquidacion::where('user_id', $this->selectedAgent->id)
            ->where('type', 'mensual')
            ->selectRaw('YEAR(desde) as year')
            ->distinct()
            ->pluck('year');

        $currentYear = Carbon::now()->year;

        if (!$savedYears->contains($currentYear)) {
            $savedYears->push($currentYear);
        }

        $this->availableYears = $savedYears->sortDesc();
    }

    public function selectYear(int $year): void
    {
        $this->selectedYear = $year;
        $this->showYearModal = false;
        $this->showMonthModal = true;
        $this->showLiquidationDisplay = false;
        $this->selectedMonth = null;
        $this->selectedMonthlyLiquidation = null;
        $this->isLiveMonthlyLiquidation = false;
        $this->liveLiquidationData = [];
        $this->dailyActivityChartData = [];
        $this->agentComparisonChartData = [];

        $this->loadAvailableMonths();
    }

    protected function loadAvailableMonths(): void
    {
        if (!$this->selectedAgent || !$this->selectedYear) {
            $this->availableMonths = collect();
            return;
        }

        $savedMonths = RegistroLiquidacion::where('user_id', $this->selectedAgent->id)
            ->where('type', 'mensual')
            ->whereYear('desde', $this->selectedYear)
            ->selectRaw('MONTH(desde) as month_num')
            ->distinct()
            ->pluck('month_num');

        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;

        if ($this->selectedYear === $currentYear && !$savedMonths->contains($currentMonth)) {
            $savedMonths->push($currentMonth);
        }

        $this->availableMonths = $savedMonths->sort()->mapWithKeys(function ($monthNum) use ($currentYear) {
            $monthName = Carbon::createFromDate($currentYear, $monthNum, 1)->locale('es')->monthName;
            return [$monthNum => ucfirst($monthName)];
        });
    }

    public function selectMonth(int $monthNum): void
    {
        $this->selectedMonth = $monthNum;
        $this->showMonthModal = false;

        $currentYear = Carbon::now()->year;
        $currentMonth = Carbon::now()->month;

        if ($this->selectedYear === $currentYear && $monthNum === $currentMonth) {
            $this->isLiveMonthlyLiquidation = true;
            $this->selectedMonthlyLiquidation = null;

            $fechaInicioCalc = Carbon::create($this->selectedYear, $this->selectedMonth, 1)->startOfDay();
            $fechaFinCalc = Carbon::now();

            $statsService = app(StatsService::class);
            $liquidationCollectionService = app(LiquidationDataCollectionService::class);

            $stats = $statsService->computeUserStats($this->selectedAgent, $fechaInicioCalc->toDateTimeString(), $fechaFinCalc->toDateTimeString());
            $lists = $liquidationCollectionService->getDetailedLists($this->selectedAgent, $fechaInicioCalc, $fechaFinCalc);

            $this->liveLiquidationData = array_merge($stats, [
                'nombre_usuario' => $this->selectedAgent->name,
                'rol' => $this->selectedAgent->getRoleNames()->first(),
                'fecha_guardado' => Carbon::now()->toDateTimeString(),
                'listas_detalladas' => $lists,
                'calculated_desde' => $fechaInicioCalc->toDateTimeString(),
                'calculated_hasta' => $fechaFinCalc->toDateTimeString(),
            ]);

            Notification::make()->title('Liquidación en vivo cargada')->body('Mostrando datos del mes actual en tiempo real.')->info()->send();
        } else {
            $this->isLiveMonthlyLiquidation = false;
            $this->liveLiquidationData = [];

            $this->selectedMonthlyLiquidation = RegistroLiquidacion::where('user_id', $this->selectedAgent->id)
                ->where('type', 'mensual')
                ->whereYear('desde', $this->selectedYear)
                ->whereMonth('desde', $this->selectedMonth)
                ->first();

            if ($this->selectedMonthlyLiquidation) {
                Notification::make()->title('Liquidación guardada cargada')->body('Mostrando liquidación mensual guardada.')->success()->send();
            } else {
                Notification::make()->title('No encontrada')->body('No se encontró la liquidación mensual guardada para la selección.')->danger()->send();
            }
        }
        
        $this->showLiquidationDisplay = true;
        
        $this->loadChartData();
    }

    /**
     * Carga los datos de los gráficos y emite eventos específicos.
     */
    protected function loadChartData(): void
    {
        if (!$this->selectedAgent || !$this->selectedYear || !$this->selectedMonth) {
            $this->dailyActivityChartData = [];
            $this->agentComparisonChartData = [];
            return;
        }

        $chartService = app(ChartDataService::class);
            
        $this->dailyActivityChartData = $chartService->getDailyActivityChartData(
            $this->selectedAgent,
            $this->selectedYear,
            $this->selectedMonth
        );

        $this->agentComparisonChartData = $chartService->getAgentComparisonChartData(
            $this->selectedYear,
            $this->selectedMonth
        );

        $this->dispatch('update-chart', [
            'chartId' => 'dailyActivity', 
            'data' => $this->dailyActivityChartData
        ]);
        $this->dispatch('update-chart', [
            'chartId' => 'agentComparison', 
            'data' => $this->agentComparisonChartData
        ]);
    }

    public function resetSelection(): void
    {
        $this->selectedAgent = null;
        $this->selectedYear = null;
        $this->selectedMonth = null;
        $this->selectedMonthlyLiquidation = null;
        $this->isLiveMonthlyLiquidation = false;
        $this->liveLiquidationData = [];
        $this->dailyActivityChartData = [];
        $this->agentComparisonChartData = [];
        $this->showYearModal = false;
        $this->showMonthModal = false;
        $this->showLiquidationDisplay = false;
        $this->loadAllAgents();
        $this->showAgentModal = true;
    }

    public function closeSelectionModals(): void
    {
        $this->showAgentModal = false;
        $this->showYearModal = false;
        $this->showMonthModal = false;
    }

    public function openDetailModal(string $tipoLista, string $titulo): void
    {
        // Verificar que todos los datos necesarios para la URL del PDF están presentes
        if (!$this->selectedAgent || !$this->selectedYear || !$this->selectedMonth) {
            Notification::make()->title('Selección Incompleta')
                ->body('Por favor, asegúrese de que un agente, año y mes estén seleccionados para ver los detalles.')
                ->warning()->send();
            return;
        }
        
        $sourceData = $this->isLiveMonthlyLiquidation ? $this->liveLiquidationData : ($this->selectedMonthlyLiquidation->datos_liquidacion ?? []);

        if (!isset($sourceData['listas_detalladas'])) {
            Notification::make()->title('Sin Datos')->body('No hay datos de liquidación cargados o listas detalladas.')->info()->send();
            return;
        }

        $listaData = $sourceData['listas_detalladas'][$tipoLista] ?? null;

        if (is_null($listaData) || empty($listaData)) {
            Notification::make()->title('Sin Datos')->body('No hay un listado detallado para este concepto en esta liquidación.')->info()->send();
            return;
        }
        
        // --- RECOPILAR PARÁMETROS ADICIONALES PARA ENVIAR AL MODAL ---
        $agentId = $this->selectedAgent->id;
        $year = $this->selectedYear;
        $month = $this->selectedMonth;

        switch ($tipoLista) {
            case 'prestamos':
                $valorCampo = str_contains(strtolower($titulo), 'con interés') ? 'valor_prestado_con_interes' : 'valor_total_prestamo';
                $this->dispatch('abrirVistaPrestamosModal', lista: $listaData, titulo: $titulo, valorCampo: $valorCampo, agentId: $agentId, year: $year, month: $month);
                break;
            case 'recaudos':
                $this->dispatch('abrirVistaAbonosModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                break;
            case 'refinanciaciones':
                 $this->dispatch('abrirVistaRefinanciacionesModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                 break;
            case 'comisiones':
                $this->dispatch('abrirVistaComisionesModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                break;
            case 'gastos':
                $this->dispatch('abrirVistaGastosModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                break;
            case 'prestamos_finalizados':
                 $this->dispatch('abrirVistaPrestamosFinalizadosModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                 break;
            case 'ajustes_dinero':
                $this->dispatch('abrirVistaAjustesModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                break;
        }
    }

    public function render()
    {
        return view('livewire.monthly-liquidations-view');
    }
}