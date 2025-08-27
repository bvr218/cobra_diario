{{-- resources/views/components/monthly-chart.blade.php --}}
@props([
    'type',
    'chartData',
    'chartId'
])

<div
    wire:ignore
    x-data="chartComponent({{ Illuminate\Support\Js::from(['type' => $type, 'chartId' => $chartId, 'data' => $chartData]) }})"

    {{--
        LA CORRECCIÓN FINAL:
        - `x-init="init()"`: Llamamos a `init` sin parámetros.
        - El componente JS accederá al elemento por sí mismo.
    --}}
    x-init="init()"

    x-destroy="destroy()"
    @update-chart.window="handleUpdate($event.detail)"
    class="liquidacion-mensual-chart-wrapper"
>
    {{-- El resto del HTML no cambia --}}
    @if($type === 'daily')
        <template x-if="data && data.totals">
            <div class="liquidacion-mensual-chart-header">
                <h3 class="liquidacion-mensual-chart-title">Actividad Diaria del Mes</h3>
                <div class="liquidacion-mensual-chart-totals">
                    <span title="Total Préstamos Nuevos">
                        <span class="total-loans" x-text="data.totals.loans"></span> Préstamos
                    </span>
                    <span title="Total Refinanciaciones">
                        <span class="total-refinancings" x-text="data.totals.refinancings"></span> Refinanc.
                    </span>
                </div>
            </div>
        </template>
    @elseif($type === 'comparison')
        <div class="liquidacion-mensual-chart-header">
            <h3 class="liquidacion-mensual-chart-title">Comparación de Productividad Mensual</h3>
        </div>
    @endif

    <div class="chart-container-scrollable">
        <div x-ref="chartContainer"></div>
    </div>
</div>