{{--
    MODIFICACIÓN 1:
    - Se añade `x-data="fullscreenChartManager()"` para inicializar el gestor del modal.
    - Se añade `@register-chart.window="registerChart($event.detail)"` para que el gestor
      "escuche" y guarde la configuración de cada gráfico cuando se renderiza.
--}}
<div
    class="liquidacion-mensual-container"
    x-data="fullscreenChartManager()"
    @register-chart.window="registerChart($event.detail)"
>
    {{-- Botón principal para iniciar la selección --}}
    <div class="liquidacion-mensual-header-actions">
        <button
            wire:click="openAgentSelectionModal"
            class="liquidacion-mensual-select-btn"
        >
            Liquidación Mensual
        </button>

        @if($showLiquidationDisplay)
            <button
                wire:click="resetSelection"
                class="liquidacion-mensual-reset-btn"
            >
                Cambiar Selección
            </button>
        @endif
    </div>

    {{-- MODALES DE SELECCIÓN (Sin cambios en esta sección) --}}
    @if($showAgentModal)
        <div class="liquidacion-mensual-modal-backdrop" wire:click="closeSelectionModals">
            <div class="liquidacion-mensual-modal-content" @click.stop>
                <h3 class="liquidacion-mensual-modal-title">Seleccionar Agente</h3>
                <div class="liquidacion-mensual-modal-grid">
                    @forelse($availableAgents as $agent)
                        <button wire:click="selectAgent({{ $agent->id }})" class="liquidacion-mensual-grid-item">
                            {{ $agent->name }}
                        </button>
                    @empty
                        <p class="liquidacion-mensual-empty-message">No se encontraron agentes con el rol "agente" en el sistema.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif

    @if($showYearModal)
        <div class="liquidacion-mensual-modal-backdrop" wire:click="closeSelectionModals">
            <div class="liquidacion-mensual-modal-content" @click.stop>
                <h3 class="liquidacion-mensual-modal-title">Seleccionar Año ({{ $selectedAgent->name ?? 'Agente' }})</h3>
                <div class="liquidacion-mensual-modal-grid">
                    @forelse($availableYears as $year)
                        <button wire:click="selectYear({{ $year }})" class="liquidacion-mensual-grid-item">
                            {{ $year }}
                        </button>
                    @empty
                        <p class="liquidacion-mensual-empty-message">No se encontraron liquidaciones mensuales para este agente en ningún año.</p>
                    @endforelse
                </div>
                <div class="liquidacion-mensual-modal-footer">
                    <button wire:click="openAgentSelectionModal" class="liquidacion-mensual-back-btn">Volver a Agentes</button>
                </div>
            </div>
        </div>
    @endif

    @if($showMonthModal)
        <div class="liquidacion-mensual-modal-backdrop" wire:click="closeSelectionModals">
            <div class="liquidacion-mensual-modal-content" @click.stop>
                <h3 class="liquidacion-mensual-modal-title">Seleccionar Mes ({{ $selectedAgent->name ?? 'Agente' }} - {{ $selectedYear }})</h3>
                <div class="liquidacion-mensual-modal-grid">
                    @forelse($availableMonths as $monthNum => $monthName)
                        <button wire:click="selectMonth({{ $monthNum }})" class="liquidacion-mensual-grid-item">
                            {{ $monthName }}
                        </button>
                    @empty
                        <p class="liquidacion-mensual-empty-message">No se encontraron liquidaciones para este agente en este año y mes.</p>
                    @endforelse
                </div>
                <div class="liquidacion-mensual-modal-footer">
                    <button wire:click="selectAgent({{ $selectedAgent->id }})" class="liquidacion-mensual-back-btn">Volver a Años</button>
                </div>
            </div>
        </div>
    @endif

    {{-- VISUALIZACIÓN DE LA LIQUIDACIÓN --}}
    @if($showLiquidationDisplay)
        @php
            $datos = $isLiveMonthlyLiquidation ? $liveLiquidationData : ($selectedMonthlyLiquidation->datos_liquidacion ?? []);
            $periodoDesde = $isLiveMonthlyLiquidation ? \Illuminate\Support\Carbon::parse($datos['calculated_desde'] ?? now()->startOfMonth()) : ($selectedMonthlyLiquidation->desde ?? null);
            $periodoHasta = $isLiveMonthlyLiquidation ? \Illuminate\Support\Carbon::parse($datos['calculated_hasta'] ?? now()) : ($selectedMonthlyLiquidation->hasta ?? null);
            $fechaGuardadoDisplay = $isLiveMonthlyLiquidation ? \Illuminate\Support\Carbon::parse($datos['fecha_guardado'] ?? now()) : \Illuminate\Support\Carbon::parse($datos['fecha_guardado'] ?? ($selectedMonthlyLiquidation->created_at ?? null));
            $tituloPrincipal = $isLiveMonthlyLiquidation ? 'Liquidación en Vivo' : ($selectedMonthlyLiquidation->nombre ?? 'Liquidación Guardada');
        @endphp

        <div class="liquidacion-mensual-display-wrapper">
            <div class="liquidacion-mensual-display-header">
                <h1 class="liquidacion-mensual-display-title">
                    {{ $tituloPrincipal }}
                    @if($isLiveMonthlyLiquidation)
                        <span class="text-sm font-normal text-gray-500 dark:text-gray-400"> (Mes Actual)</span>
                    @endif
                </h1>
                <p class="liquidacion-mensual-display-subtitle">
                    Liquidación de: <strong class="liquidacion-mensual-display-user">{{ $datos['nombre_usuario'] ?? 'N/A' }}</strong>
                    <span class="liquidacion-mensual-display-role">({{ $datos['rol'] ?? 'N/A' }})</span>
                </p>
                <p class="liquidacion-mensual-display-period">
                    Período:
                    <strong>{{ $periodoDesde ? $periodoDesde->format('d/m/Y h:i A') : 'N/A' }}</strong>
                    al
                    <strong>{{ $periodoHasta ? $periodoHasta->format('d/m/Y h:i A') : 'N/A' }}</strong>
                </p>
                <p class="liquidacion-mensual-display-timestamp">
                    ({{ $isLiveMonthlyLiquidation ? 'Datos actualizados al momento: ' : 'Liquidación guardada el: ' }}{{ $fechaGuardadoDisplay ? $fechaGuardadoDisplay->format('d/m/Y h:i:s A') : 'N/A' }})
                </p>
            </div>

            <div class="liquidacion-mensual-data-grid">
                {{-- Dinero Inicial --}}
                <div class="liquidacion-mensual-data-item" title="Monto base con el que el usuario inició sus operaciones.">
                    <h3 class="liquidacion-mensual-data-item-title">Dinero Inicial</h3>
                    <span class="liquidacion-mensual-data-item-value">${{ number_format($datos['dineroInicial'] ?? 0, 0, ',', '.') }}</span>
                </div>
                {{-- Dinero Capital --}}
                <div class="liquidacion-mensual-data-item" title="Dinero total disponible para el usuario, incluyendo el monto inicial y ajustes, en el momento del guardado.">
                    <h3 class="liquidacion-mensual-data-item-title">Dinero Capital</h3>
                    <span class="liquidacion-mensual-data-item-value">${{ number_format($datos['dineroCapital'] ?? 0, 0, ',', '.') }}</span>
                </div>
                {{-- Dinero en Caja --}}
                <div class="liquidacion-mensual-data-item" title="Dinero físico que el agente debería tener en el momento del guardado.">
                    <h3 class="liquidacion-mensual-data-item-title">Dinero en Caja</h3>
                    <span class="liquidacion-mensual-data-item-value">${{ number_format($datos['dineroEnMano'] ?? 0, 0, ',', '.') }}</span>
                </div>
                {{-- Préstamos Entregados --}}
                <div wire:click="openDetailModal('prestamos', 'Detalle de Préstamos Entregados')" class="liquidacion-mensual-data-item liquidacion-mensual-clickable" title="Cantidad de préstamos autorizados/activos en el período guardado. (Entre paréntesis: préstamos pendientes de aprobación). Haz clic para ver detalles.">
                    <h3 class="liquidacion-mensual-data-item-title">Préstamos Entregados</h3>
                    <span class="liquidacion-mensual-data-item-value">{{ number_format($datos['prestamosEntregados'] ?? 0) }} ({{ number_format($datos['prestamosPendientes'] ?? 0) }})</span>
                </div>
                {{-- Total Prestado --}}
                <div wire:click="openDetailModal('prestamos', 'Detalle de Total Prestado')" class="liquidacion-mensual-data-item liquidacion-mensual-clickable" title="Suma total del capital prestado en el período seleccionado, sin incluir intereses. Haz clic para ver detalles.">
                    <h3 class="liquidacion-mensual-data-item-title">Total Prestado</h3>
                    <span class="liquidacion-mensual-data-item-value">${{ number_format($datos['totalPrestado'] ?? 0, 0, ',', '.') }}</span>
                </div>
                {{-- Total Prestado (Con Interés) --}}
                <div wire:click="openDetailModal('prestamos', 'Detalle de Total Prestado (Con Interés)')" class="liquidacion-mensual-data-item liquidacion-mensual-clickable" title="Suma total del valor de los préstamos en el período, incluyendo los intereses. Haz clic para ver detalles.">
                    <h3 class="liquidacion-mensual-data-item-title">Total Prestado (Con Interés)</h3>
                    <span class="liquidacion-mensual-data-item-value">${{ number_format($datos['totalPrestadoConInteres'] ?? 0, 0, ',', '.') }}</span>
                </div>
                {{-- Cantidad de Refinanciaciones --}}
                <div wire:click="openDetailModal('refinanciaciones', 'Detalle de Refinanciaciones')" class="liquidacion-mensual-data-item liquidacion-mensual-clickable" title="Cantidad de refinanciaciones autorizadas en el período. (Entre paréntesis: refinanciaciones pendientes de aprobación). Haz clic para ver detalles.">
                    <h3 class="liquidacion-mensual-data-item-title">Cantidad de Refinanciaciones</h3>
                    <span class="liquidacion-mensual-data-item-value">{{ number_format($datos['cantidadRefinanciaciones'] ?? 0) }} ({{ number_format($datos['cantidadRefinanciacionesPendientes'] ?? 0) }})</span>
                </div>
                {{-- Valor Total de Refinanciaciones --}}
                <div wire:click="openDetailModal('refinanciaciones', 'Detalle de Valor Total de Refinanciaciones')" class="liquidacion-mensual-data-item liquidacion-mensual-clickable" title="Suma de la deuda anterior que se refinanció. (Entre paréntesis: monto de dinero nuevo añadido en las refinanciaciones). Haz clic para ver detalles.">
                    <h3 class="liquidacion-mensual-data-item-title">Valor Total de Refinanciaciones</h3>
                    <span class="liquidacion-mensual-data-item-value">${{ number_format($datos['deudaRefinanciadaTotal'] ?? 0, 0, ',', '.') }} (${{ number_format($datos['montoRefinanciaciones'] ?? 0, 0, ',', '.') }})</span>
                </div>
                {{-- Total Seguros --}}
                <div wire:click="openDetailModal('comisiones', 'Detalle de Seguros Cobrados')" class="liquidacion-mensual-data-item liquidacion-mensual-clickable" title="Suma de todos los seguros (comisiones) cobrados en el período seleccionado. Haz clic para ver detalles.">
                    <h3 class="liquidacion-mensual-data-item-title">Total Seguros</h3>
                    <span class="liquidacion-mensual-data-item-value">${{ number_format($datos['totalComision'] ?? 0, 0, ',', '.') }}</span>
                </div>
                {{-- Préstamos Finalizados --}}
                <div wire:click="openDetailModal('prestamos_finalizados', 'Detalle de Préstamos Finalizados')" class="liquidacion-mensual-data-item liquidacion-mensual-clickable" title="Cantidad de préstamos completados en el período. Haz clic para ver detalles.">
                    <h3 class="liquidacion-mensual-data-item-title">Préstamos Finalizados</h3>
                    <span class="liquidacion-mensual-data-item-value">{{ number_format($datos['prestamosFinalizadosCount'] ?? 0) }}</span>
                </div>
                {{-- Recaudos Realizados --}}
                <div wire:click="openDetailModal('recaudos', 'Detalle de Recaudos Realizados')" class="liquidacion-mensual-data-item liquidacion-mensual-clickable" title="Cantidad de abonos recibidos en el período. (Entre paréntesis: total de préstamos históricos asignados a este agente). Haz clic para ver detalles.">
                    <h3 class="liquidacion-mensual-data-item-title">Recaudos Realizados</h3>
                    <span class="liquidacion-mensual-data-item-value">{{ number_format($datos['cantidadRecaudosRealizados'] ?? 0) }} ({{ number_format($datos['totalPrestamosAsignados'] ?? 0) }})</span>
                </div>
                {{-- Dinero Recaudado --}}
                <div wire:click="openDetailModal('recaudos', 'Detalle de Dinero Recaudado')" class="liquidacion-mensual-data-item liquidacion-mensual-clickable" title="Suma total del dinero recaudado a través de abonos en el período seleccionado. Haz clic para ver detalles.">
                    <h3 class="liquidacion-mensual-data-item-title">Dinero Recaudado</h3>
                    <span class="liquidacion-mensual-data-item-value">${{ number_format($datos['dineroRecaudado'] ?? 0, 0, ',', '.') }}</span>
                </div>
                {{-- Gastos --}}
                <div wire:click="openDetailModal('gastos', 'Detalle de Gastos')" class="liquidacion-mensual-data-item liquidacion-mensual-clickable" title="Suma de gastos autorizados. (Entre paréntesis: suma de gastos pendientes de autorizar). Haz clic para ver detalles.">
                    <h3 class="liquidacion-mensual-data-item-title">Gastos</h3>
                    <span class="liquidacion-mensual-data-item-value">${{ number_format($datos['gastosAutorizados'] ?? 0, 0, ',', '.') }} (${{ number_format($datos['gastosNoAutorizados'] ?? 0, 0, ',', '.') }})</span>
                </div>
                {{-- Nuevo Cuadro: Ajustes de dinero --}}
                <div wire:click="openDetailModal('ajustes_dinero', 'Detalle de Ajustes de Dinero')" class="liquidacion-mensual-data-item liquidacion-mensual-clickable" title="Cantidad de ajustes de dinero realizados en el período. Haz clic para ver detalles.">
                    <h3 class="liquidacion-mensual-data-item-title">Ajustes de dinero</h3>
                    <span class="liquidacion-mensual-data-item-value">{{ number_format($datos['ajustesDineroCount'] ?? 0) }}</span>
                </div>
                {{-- Dinero en Mano Final --}}
                <div class="liquidacion-mensual-data-item" title="Balance final calculado. Este es el dinero que el agente debía entregar.">
                    <h3 class="liquidacion-mensual-data-item-title">Dinero en Mano</h3>
                    @php $dineroEnCaja = $datos['dineroEnCaja'] ?? 0; @endphp
                    <span class="liquidacion-mensual-data-item-value {{ $dineroEnCaja < 0 ? 'liquidacion-mensual-value--negative' : 'liquidacion-mensual-value--positive' }}">
                        {{ $dineroEnCaja < 0 ? '-' : '' }}${{ number_format(abs($dineroEnCaja), 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- SECCIÓN DE GRÁFICOS CON RENDERIZADO CONDICIONAL --}}
            <div class="liquidacion-mensual-charts-grid">

                {{-- Solo renderiza el componente del gráfico si hay datos en la serie --}}
                @if(!empty($dailyActivityChartData['series'][0]['data']))
                    {{--
                        MODIFICACIÓN 2:
                        - Se añade un div contenedor para posicionar el botón de pantalla completa.
                        - Se añade el botón que llama a `openModal()` con el ID del gráfico.
                    --}}
                    <div class="liquidacion-mensual-chart-wrapper-fullscreen">
                        <button @click="openModal('dailyActivity')" class="chart-fullscreen-btn" title="Ver en pantalla completa">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                            </svg>
                        </button>
                        <x-monthly-chart
                            type="daily"
                            chartId="dailyActivity"
                            :chart-data="$dailyActivityChartData"
                        />
                    </div>
                @endif

                @if(!empty($agentComparisonChartData['series'][0]['data']))
                    {{-- Se repite la misma estructura para el segundo gráfico --}}
                    <div class="liquidacion-mensual-chart-wrapper-fullscreen">
                         <button @click="openModal('agentComparison')" class="chart-fullscreen-btn" title="Ver en pantalla completa">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15" />
                            </svg>
                        </button>
                        <x-monthly-chart
                            type="comparison"
                            chartId="agentComparison"
                            :chart-data="$agentComparisonChartData"
                        />
                    </div>
                @endif

            </div>

        </div>
    @elseif(!$showAgentModal && !$showYearModal && !$showMonthModal && !$showLiquidationDisplay)
        <div class="liquidacion-mensual-empty-state">
            <p class="liquidacion-mensual-empty-state-text">
                Haz clic en "Liquidación Mensual" para empezar a ver los resúmenes.
            </p>
        </div>
    @endif

    {{-- MODALES DE DETALLE (Sin cambios en esta sección) --}}
    <livewire:vista-liquidacion.vista-prestamos-modal />
    <livewire:vista-liquidacion.vista-abonos-modal />
    <livewire:vista-liquidacion.vista-refinanciaciones-modal />
    <livewire:vista-liquidacion.vista-comisiones-modal />
    <livewire:vista-liquidacion.vista-gastos-modal />
    <livewire:vista-liquidacion.vista-prestamos-finalizados-modal />
    <livewire:vista-liquidacion.vista-ajustes-modal />

    <div x-show="isModalOpen" class="chart-fullscreen-modal-backdrop" style="display: none;">
        <div @click.outside="closeModal()" @keydown.escape.window="closeModal()" class="chart-fullscreen-modal-content">
            <button @click="closeModal()" class="chart-fullscreen-close-btn" title="Cerrar">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
            </button>
            <div class="chart-fullscreen-rotate-device-prompt">
                 <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-12 h-12">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                </svg>
                <span>Gira tu dispositivo para ver mejor el gráfico</span>
            </div>
            <div x-ref="modalChartContainer" class="w-full h-full"></div>
        </div>
    </div>

</div>