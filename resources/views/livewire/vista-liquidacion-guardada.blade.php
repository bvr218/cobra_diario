<div class="liquidation-view">
    @if($liquidacion)
        <div class="liquidation-view__container">

            {{-- ENCABEZADO ESTÁTICO DE LA LIQUIDACIÓN --}}
            <div class="liquidation-header">
                <h1 class="liquidation-header__title">{{ $liquidacion->nombre }}</h1>
                <p class="liquidation-header__subtitle">
                    Liquidación de: <strong class="liquidation-header__user">{{ $datos['nombre_usuario'] ?? $liquidacion->user->name }}</strong>
                    <span class="liquidation-header__role">({{ $datos['rol'] ?? 'N/A' }})</span>
                </p>
                <p class="liquidation-header__period">
                    Período registrado:
                    <strong>{{ \Carbon\Carbon::parse($liquidacion->desde)->format('d/m/Y h:i A') }}</strong>
                    al
                    <strong>{{ \Carbon\Carbon::parse($liquidacion->hasta)->format('d/m/Y h:i A') }}</strong>
                </p>
                <p class="liquidation-header__timestamp">
                    (Liquidación guardada el: {{ \Carbon\Carbon::parse($datos['fecha_guardado'] ?? $liquidacion->created_at)->format('d/m/Y h:i:s A') }})
                </p>
            </div>

            {{-- CONTENEDOR DE DATOS - AHORA INTERACTIVO --}}
            <div class="data-container">
                <div class="info-flex-container">
                    {{-- Dinero Inicial (No es una lista, no es clickeable) --}}
                    <div class="info-item-container" title="Monto base con el que el usuario inició sus operaciones.">
                        <h3 class="info-item__title">Dinero Inicial</h3>
                        <span class="info-item__value">
                            ${{ number_format($datos['dineroInicial'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- Dinero Capital (No es una lista, no es clickeable) --}}
                    <div class="info-item-container" title="Dinero total disponible para el usuario, incluyendo el monto inicial y ajustes, en el momento del guardado.">
                        <h3 class="info-item__title">Dinero Capital</h3>
                        <span class="info-item__value">
                            ${{ number_format($datos['dineroCapital'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- Dinero en Caja (Aclaración: en tu original usas 'dineroEnMano' para este cuadro, lo mantengo así por consistencia) --}}
                    <div class="info-item-container" title="Dinero físico que el agente debería tener en el momento del guardado.">
                        <h3 class="info-item__title">Dinero en Caja</h3>
                        <span class="info-item__value">
                            ${{ number_format($datos['dineroEnMano'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- Préstamos Entregados --}}
                    <div wire:click="abrirModal('prestamos', 'Detalle de Préstamos Entregados')" class="info-item-container cursor-pointer" title="Cantidad de préstamos autorizados/activos en el período guardado. (Entre paréntesis: préstamos pendientes de aprobación). Haz clic para ver detalles.">
                        <h3 class="info-item__title">Préstamos Entregados</h3>
                        <span class="info-item__value">
                            {{ number_format($datos['prestamosEntregados'] ?? 0) }} ({{ number_format($datos['prestamosPendientes'] ?? 0) }})
                        </span>
                    </div>

                    {{-- Total Prestado --}}
                    <div wire:click="abrirModal('prestamos', 'Detalle de Total Prestado')" class="info-item-container cursor-pointer" title="Suma total del capital prestado en el período seleccionado, sin incluir intereses. Haz clic para ver detalles.">
                        <h3 class="info-item__title">Total Prestado</h3>
                        <span class="info-item__value">
                            ${{ number_format($datos['totalPrestado'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- Total Prestado (Con Interés) --}}
                    <div wire:click="abrirModal('prestamos', 'Detalle de Total Prestado (Con Interés)')" class="info-item-container cursor-pointer" title="Suma total del valor de los préstamos en el período, incluyendo los intereses. Haz clic para ver detalles.">
                        <h3 class="info-item__title">Total Prestado (Con Interés)</h3>
                        <span class="info-item__value">
                            ${{ number_format($datos['totalPrestadoConInteres'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- Cantidad de Refinanciaciones --}}
                    <div wire:click="abrirModal('refinanciaciones', 'Detalle de Refinanciaciones')" class="info-item-container cursor-pointer" title="Cantidad de refinanciaciones autorizadas en el período. (Entre paréntesis: refinanciaciones pendientes de aprobación). Haz clic para ver detalles.">
                        <h3 class="info-item__title">Cantidad de Refinanciaciones</h3>
                        <span class="info-item__value">
                             {{ number_format($datos['cantidadRefinanciaciones'] ?? 0) }} ({{ number_format($datos['cantidadRefinanciacionesPendientes'] ?? 0) }})
                        </span>
                    </div>

                    {{-- Valor Total de Refinanciaciones --}}
                    <div wire:click="abrirModal('refinanciaciones', 'Detalle de Valor Total de Refinanciaciones')" class="info-item-container cursor-pointer" title="Suma de la deuda anterior que se refinanció. (Entre paréntesis: monto de dinero nuevo añadido en las refinanciaciones). Haz clic para ver detalles.">
                        <h3 class="info-item__title">Valor Total de Refinanciaciones</h3>
                        <span class="info-item__value">
                            ${{ number_format($datos['deudaRefinanciadaTotal'] ?? 0, 0, ',', '.') }}
                            (${{ number_format($datos['montoRefinanciaciones'] ?? 0, 0, ',', '.') }})
                        </span>
                    </div>

                    {{-- Total Seguros --}}
                    <div wire:click="abrirModal('comisiones', 'Detalle de Seguros Cobrados')" class="info-item-container cursor-pointer" title="Suma de todos los seguros (comisiones) cobrados en el período seleccionado. Haz clic para ver detalles.">
                        <h3 class="info-item__title">Total Seguros</h3>
                        <span class="info-item__value">
                            ${{ number_format($datos['totalComision'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- Préstamos Finalizados --}}
                    <div wire:click="abrirModal('prestamos_finalizados', 'Detalle de Préstamos Finalizados')" class="info-item-container cursor-pointer" title="Cantidad de préstamos completados en el período. Haz clic para ver detalles.">
                        <h3 class="info-item__title">Préstamos Finalizados</h3>
                        <span class="info-item__value">
                            {{ number_format($datos['prestamosFinalizadosCount'] ?? 0) }}
                        </span>
                    </div>

                    {{-- Recaudos Realizados --}}
                    <div wire:click="abrirModal('recaudos', 'Detalle de Recaudos Realizados')" class="info-item-container cursor-pointer" title="Cantidad de abonos recibidos en el período. (Entre paréntesis: total de préstamos históricos asignados a este agente). Haz clic para ver detalles.">
                        <h3 class="info-item__title">Recaudos Realizados</h3>
                        <span class="info-item__value">
                            {{ number_format($datos['cantidadRecaudosRealizados'] ?? 0) }} ({{ number_format($datos['totalPrestamosAsignados'] ?? 0) }})
                        </span>
                    </div>

                    {{-- Dinero Recaudado --}}
                    <div wire:click="abrirModal('recaudos', 'Detalle de Dinero Recaudado')" class="info-item-container cursor-pointer" title="Suma total del dinero recaudado a través de abonos en el período seleccionado. Haz clic para ver detalles.">
                        <h3 class="info-item__title">Dinero Recaudado</h3>
                        <span class="info-item__value">
                             ${{ number_format($datos['dineroRecaudado'] ?? 0, 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- Gastos --}}
                    <div wire:click="abrirModal('gastos', 'Detalle de Gastos')" class="info-item-container cursor-pointer" title="Suma de gastos autorizados. (Entre paréntesis: suma de gastos pendientes de autorizar). Haz clic para ver detalles.">
                        <h3 class="info-item__title">Gastos</h3>
                        <span class="info-item__value">
                             ${{ number_format($datos['gastosAutorizados'] ?? 0, 0, ',', '.') }}
                            (${{ number_format($datos['gastosNoAutorizados'] ?? 0, 0, ',', '.') }})
                        </span>
                    </div>

                    {{-- Nuevo Cuadro: Ajustes de dinero --}}
                    <div wire:click="abrirModal('ajustes_dinero', 'Detalle de Ajustes de Dinero')" class="info-item-container cursor-pointer" title="Cantidad de ajustes de dinero realizados en el período guardado. Haz clic para ver detalles.">
                        <h3 class="info-item__title">Ajustes de dinero</h3>
                        <span class="info-item__value">
                            {{ number_format($datos['ajustesDineroCount'] ?? 0) }}
                        </span>
                    </div>

                    {{-- Dinero en Mano Final (No es una lista, no es clickeable) --}}
                    <div class="info-item-container" title="Balance final calculado. Este es el dinero que el agente debía entregar.">
                        <h3 class="info-item__title">Dinero en Mano</h3>
                        @php
                            $dineroEnCaja = $datos['dineroEnCaja'] ?? 0;
                        @endphp
                        <span class="info-item__value {{ $dineroEnCaja < 0 ? 'info-item__value--negative' : 'info-item__value--positive' }}">
                            {{ $dineroEnCaja < 0 ? '-' : '' }}${{ number_format(abs($dineroEnCaja), 0, ',', '.') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- *** INCLUIR LOS MODALES DE SOLO LECTURA *** --}}
        <livewire:vista-liquidacion.vista-prestamos-modal />
        <livewire:vista-liquidacion.vista-abonos-modal />
        <livewire:vista-liquidacion.vista-refinanciaciones-modal />
        <livewire:vista-liquidacion.vista-comisiones-modal />
        <livewire:vista-liquidacion.vista-gastos-modal />
        <livewire:vista-liquidacion.vista-prestamos-finalizados-modal />
        <livewire:vista-liquidacion.vista-ajustes-modal />

    @else
        {{-- Mensaje de error si la liquidación no se puede cargar --}}
        <div class="liquidation-not-found">
            <h2 class="liquidation-not-found__title">Liquidación no encontrada</h2>
            <p class="liquidation-not-found__text">No se pudo cargar el registro de la liquidación solicitada. Por favor, vuelve a la lista e inténtalo de nuevo.</p>
        </div>
    @endif
</div>