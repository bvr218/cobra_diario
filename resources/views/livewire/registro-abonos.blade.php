{{-- resources/views/livewire/registro-abonos.blade.php --}}
<div>
    <div class="p-4 sm:p-6">
        {{-- Tabs de roles --}}
        <div class="flex justify-center space-x-2 mb-4">
            @foreach (['Oficina' => 'Liquidación de Oficina', 'Agente' => 'Liquidación de Agente'] as $key => $label)
                <button
                    wire:click="setActiveRol('{{ $key }}')"
                    class="tab-button {{ $rolSeleccionado === $key ? 'active-tab' : '' }}"
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Selector de fecha y HORA --}}
        @if($usuarioSeleccionado)
            <div class="date-selector-container">
                {{-- En este div agruparemos los controles de fecha para que se mantengan juntos --}}
                <div class="date-controls-wrapper">
                    @unless($lockDateFilter)
                        {{-- INICIO DE LA LÓGICA RESTAURADA --}}
                        <div class="date-selector-radio-group">
                            <label class="date-selector-radio-label">
                                <input type="radio"
                                       wire:model.live="filtrarPorFecha"
                                       value="0"
                                       wire:change="computeStats">
                                Todos los Días
                            </label>
                            <label class="date-selector-radio-label">
                                <input type="radio"
                                       wire:model.live="filtrarPorFecha"
                                       value="1"
                                       wire:change="computeStats">
                                Día Individual
                            </label>
                        </div>
                        
                        @if($filtrarPorFecha)
                            <div class="flex flex-col sm:flex-row gap-4 sm:gap-2 mt-2 sm:mt-0">
                                {{-- Input "Desde" --}}
                                <div class="flex flex-col flex-1">
                                    <label for="fechaInicio"
                                           class="mb-1 font-semibold text-gray-700 dark:text-gray-300">
                                        Desde (Fecha y Hora)
                                    </label>
                                    <input id="fechaInicio"
                                           type="datetime-local"
                                           wire:model.live="fechaInicio"
                                           wire:change="computeStats"
                                           class="date-selector-input">
                                </div>
                                {{-- Input "Hasta" --}}
                                <div class="flex flex-col flex-1">
                                    <label for="fechaFin"
                                           class="mb-1 font-semibold text-gray-700 dark:text-gray-300">
                                        Hasta (Fecha y Hora)
                                    </label>
                                    <input id="fechaFin"
                                           type="datetime-local"
                                           wire:model.live="fechaFin"
                                           wire:change="computeStats"
                                           class="date-selector-input">
                                </div>
                            </div>
                        @endif
                        {{-- FIN DE LA LÓGICA RESTAURADA --}}
                    @else
                        {{-- Rango fijo para usuario con permiso registro.view --}}
                        <div class="p-2 bg-gray-100 rounded">
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Liquidaciones del día:
                                <strong>{{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y H:i') }}</strong>
                                 al
                                <strong>{{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y H:i') }}</strong>
                            </p>
                        </div>
                    @endunless
                </div>

                {{-- Contenedor y Label para el Botón de Recarga --}}
                <div class="flex flex-col">
                    {{-- Este label invisible alinea el botón con los inputs de fecha (si están visibles) --}}
                    <label class="mb-1 font-semibold text-gray-700 dark:text-gray-300">
                          {{-- Espacio en blanco sin salto de línea para ocupar altura --}}
                    </label>
                    
                    <button
                        type="button"
                        wire:click="reloadStats"
                        class="reload-button"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        Recargar
                    </button>
                </div>
            </div>
        @endif {{-- Cierre del @if($usuarioSeleccionado) --}}

        {{-- Modal lista de usuarios --}}
        @if($showList)
            <div class="modal-container">
                <button wire:click="closeList" class="close-btn">×</button>
                <div class="users-grid">
                    @foreach($usuarios as $u)
                        <button wire:click="selectUsuario({{ $u->id }})" class="user-btn">
                            {{ $u->name }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Datos del usuario --}}
        @if($usuarioSeleccionado)
            <div class="data-container mx-auto mt-6" wire:poll.5000ms="computeStats">
                <h2 class="text-lg sm:text-xl font-semibold text-center mb-4 text-gray-900 dark:text-gray-100">
                    <strong>
                        {{ $rolSeleccionado === 'Oficina' ? 'Oficina:' : 'Agente:' }}
                    </strong>
                    {{ $usuarioSeleccionado->name }}
                </h2>

                <div class="info-flex-container">
                    {{-- Dinero Inicial --}}
                    <div class="info-item-container" title="Monto base con el que el usuario inicia sus operaciones.">
                        <h3>Dinero Inicial</h3>
                        <span class="info-value">
                            $<span x-data="{ amount: @js($dineroInicial) }"
                                     x-text="new Intl.NumberFormat('es-CO').format(amount)"></span>
                        </span>
                    </div>

                    {{-- Dinero Capital --}}
                    <div class="info-item-container" title="Dinero total disponible para el usuario, incluyendo el monto inicial y ajustes.">
                        <h3>Dinero Capital</h3>
                        <span class="info-value">
                            $<span x-data="{ amount: @js($dineroCapital) }"
                                     x-text="new Intl.NumberFormat('es-CO').format(amount)"></span>
                        </span>
                    </div>

                    {{-- Dinero en Caja --}}
                    <div class="info-item-container" title="Dinero físico que el agente debería tener.">
                        <h3>Dinero en Caja</h3>
                        <span class="info-value">
                            $<span x-data="{ amount: @js($dineroEnMano) }"
                                     x-text="new Intl.NumberFormat('es-CO').format(amount)"></span>
                        </span>
                    </div>

                    {{-- Préstamos Entregados --}}
                    <div wire:click="abrirModalPrestamosEntregadosClick"
                         class="info-item-container cursor-pointer" title="Cantidad de préstamos autorizados/activos en el período. (Entre paréntesis: préstamos pendientes de aprobación).">
                        <h3>Préstamos Entregados</h3>
                        <span class="info-value">
                            {{ $prestamosEntregados }} ({{ $prestamosPendientes }})
                        </span>
                    </div>

                    {{-- Total Prestado --}}
                    <div wire:click="abrirModalTotalPrestadoClick"
                         class="info-item-container cursor-pointer" title="Suma total del capital prestado en el período seleccionado, sin incluir intereses.">
                        <h3>Total Prestado</h3>
                        <span class="info-value">
                            ${{ number_format($totalPrestado, 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- Total Prestado (Con Interés) --}}
                    <div wire:click="abrirModalTotalPrestadoConInteresClick"
                         class="info-item-container cursor-pointer" title="Suma total del valor de los préstamos en el período, incluyendo los intereses.">
                        <h3>Total Prestado (Con Interés)</h3>
                        <span class="info-value">
                            ${{ number_format($totalPrestadoConInteres, 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- Cantidad de Refinanciaciones --}}
                    <div wire:click="abrirModalCantidadRefinanciacionesClick"
                         class="info-item-container cursor-pointer" title="Cantidad de refinanciaciones autorizadas en el período. (Entre paréntesis: refinanciaciones pendientes de aprobación).">
                        <h3>Cantidad de Refinanciaciones</h3>
                        <span class="info-value">
                            {{ $cantidadRefinanciaciones }} ({{ $cantidadRefinanciacionesPendientes }})
                        </span>
                    </div>

                    {{-- Valor Total de Refinanciaciones --}}
                    <div wire:click="abrirModalValorTotalRefinanciacionesClick"
                         class="info-item-container cursor-pointer" title="Suma de la deuda anterior que se refinanció. (Entre paréntesis: monto de dinero nuevo añadido en las refinanciaciones).">
                        <h3>Valor Total de Refinanciaciones</h3>
                        <span class="info-value">
                            $<span x-data="{ amount: @js($deudaRefinanciadaTotal) }"
                                     x-text="new Intl.NumberFormat('es-CO').format(amount)"></span>
                            (${{ number_format($montoRefinanciaciones, 0, ',', '.') }})
                        </span>
                    </div>

                    <!-- {{-- Valor de Refinanciaciones (Con Interés) --}}
                    <div wire:click="abrirModalValorRefinanciacionesConInteresClick"
                         class="info-item-container cursor-pointer" title="Suma total del valor de las refinanciaciones, incluyendo los intereses.">
                        <h3>Valor de Refinanciaciones (Con Interés)</h3>
                        <span class="info-value">
                            $<span x-data="{ amount: @js($deudaRefinanciadaInteresTotal) }"
                                     x-text="new Intl.NumberFormat('es-CO').format(amount)"></span>
                        </span>
                    </div> -->

                    {{-- Total Seguros --}}
                    <div wire:click="abrirModalComisionesRegistradasClick"
                         class="info-item-container cursor-pointer" title="Suma de todos los seguros (comisiones) cobrados en el período seleccionado.">
                        <h3>Total Seguros</h3>
                        <span class="info-value">
                            ${{ number_format($totalComision, 0, ',', '.') }}
                        </span>
                    </div>

                    {{-- NUEVO CUADRO: Préstamos Finalizados --}}
                    <div wire:click="abrirModalPrestamosFinalizadosClick"
                         class="info-item-container cursor-pointer" title="Cantidad de préstamos completados en el período. Haz clic para ver el detalle.">
                        <h3>Préstamos Finalizados</h3>
                        <span class="info-value">
                            {{ $prestamosFinalizadosCount }}
                        </span>
                    </div>

                    {{-- Recaudos Realizados --}}
                    <div wire:click="abrirModalRecaudosRealizadosClick"
                         class="info-item-container cursor-pointer" title="Cantidad de abonos recibidos en el período. (Entre paréntesis: total de préstamos históricos asignados a este agente).">
                        <h3>Recaudos Realizados</h3>
                        <span class="info-value">
                            {{ $cantidadRecaudosRealizados }} ({{ $totalPrestamosAsignados }})
                        </span>
                    </div>

                    {{-- Dinero Recaudado --}}
                    <div wire:click="abrirModalDineroRecaudadoClick"
                         class="info-item-container cursor-pointer" title="Suma total del dinero recaudado a través de abonos en el período seleccionado.">
                        <h3>Dinero Recaudado</h3>
                        <span class="info-value">
                            $<span x-data="{ amount: @js($dineroRecaudado) }"
                                     x-text="new Intl.NumberFormat('es-CO').format(amount)"></span>
                        </span>
                    </div>

                    {{-- Gastos --}}
                    <div wire:click="abrirModalGastosAutorizadosClick"
                         class="info-item-container cursor-pointer" title="Suma de gastos autorizados. (Entre paréntesis: suma de gastos pendientes de autorizar).">
                        <h3>Gastos</h3>
                        <span class="info-value">
                            $<span x-data="{ amount: @js($gastosAutorizados) }"
                                     x-text="new Intl.NumberFormat('es-CO').format(amount)"></span>
                            ($<span x-data="{ amount: @js($gastosNoAutorizados) }"
                                     x-text="new Intl.NumberFormat('es-CO').format(amount)"></span>)
                        </span>
                    </div>


                    {{-- NUEVO CUADRO: Ajustes de dinero --}}
                    <div wire:click="abrirModalAjustesDineroClick"
                        class="info-item-container cursor-pointer" title="Cantidad de ajustes de dinero realizados en el período. Haz clic para ver el detalle.">
                        <h3>Ajustes de dinero</h3>
                        <span class="info-value">
                            {{ $ajustesDineroCount }}
                        </span>
                    </div>

                    {{-- Dinero en Mano Final --}}
                    <div class="info-item-container" title="Balance final calculado: (Dinero Inicial + Recaudado) - (Total Prestado + Gastos Autorizados). Este es el dinero que el agente debe entregar.">
                        <h3>Dinero en Mano</h3>
                        <span class="info-value"
                              style="color: {{ $dineroEnCaja < 0 ? 'var(--accent-color)' : 'var(--secondary-color)' }}">
                            {{ $dineroEnCaja < 0 ? '-' : '' }}${{ number_format(abs($dineroEnCaja), 0, ',', '.') }}
                        </span>
                    </div>
                </div>

                {{-- Botones de acción --}}
                <x-action-buttons
                    :usuarioSeleccionado="$usuarioSeleccionado"
                    :filtrarPorFecha="$filtrarPorFecha"
                    :fechaInicio="$fechaInicio"
                    :fechaFin="$fechaFin"
                />

                <div class="mt-6 flex justify-center">
                    @if($filtrarPorFecha)
                        <button
                            wire:click="openGuardarLiquidacionModal"
                            class="save-liquidation-button"
                        >
                            Guardar Liquidación
                        </button>
                    @endif
                </div>
            </div>

            {{-- Gráficos y modales adicionales --}}
            <livewire:loan-charts
                :usuarioSeleccionadoId="$usuarioSeleccionado->id"
                :rolSeleccionado="$rolSeleccionado"
                wire:key="loan-charts-{{ $usuarioSeleccionado->id }}"
            />
            <livewire:adjust-money-modal
                :userId="$usuarioSeleccionado->id"
                wire:key="adjust-money-modal-{{ $usuarioSeleccionado->id }}"
            />
        @endif

        {{-- Mensajes si no hay rol o usuarios --}}
        @if(!$rolSeleccionado)
            <p class="text-center text-gray-500 dark:text-gray-400 mt-6">
                Selecciona un tipo de liquidación.
            </p>
        @endif
        @if($rolSeleccionado && $usuarios->isEmpty() && !$showList && !$usuarioSeleccionado)
            <p class="text-center text-gray-500 dark:text-gray-400 mt-6">
                No se encontraron usuarios con el rol “{{ $rolSeleccionado }}”.
            </p>
        @endif
    </div>

    {{-- Otros modales Livewire --}}
    <livewire:prestamos.prestamos-entregados-modal wire:key="prestamos-entregados-modal" />
    <livewire:comisiones.comisiones-registradas-modal wire:key="comisiones-registradas-modal" />
    <livewire:abonos.abonos-realizados-modal wire:key="abonos-realizados-modal" />
    <livewire:refinanciaciones.refinanciaciones-modal wire:key="refinanciaciones-modal" />
    <livewire:gastos-autorizados-modal wire:key="gastos-autorizados-modal" />
    <livewire:guardar-liquidacion-modal />
    <livewire:prestamos.prestamos-finalizados-modal wire:key="prestamos-finalizados-modal" />
    <livewire:ajustes-dinero-modal wire:key="ajustes-dinero-modal" />

</div>