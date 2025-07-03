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
                @unless($lockDateFilter)
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
                        <div class="flex flex-col sm:flex-row gap-4 mt-2">
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
        @endif

        {{-- Modal lista de usuarios --}}
        @if($showList)
            <div class="modal-container">
                <button wire:click="closeList" class="close-btn">&times;</button>
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
                            $<span x-data="{ amount: @js($dineroInicialDelUsuario) }"
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
                    <div class="info-item-container cursor-pointer"
                         wire:click="openTransferenciaDineroEnManoModal" title="Dinero físico que el agente debería tener. Se puede transferir desde/hacia el dinero en mano.">
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

                    {{-- Valor de Refinanciaciones (Con Interés) --}}
                    <div wire:click="abrirModalValorRefinanciacionesConInteresClick"
                         class="info-item-container cursor-pointer" title="Suma total del valor de las refinanciaciones, incluyendo los intereses.">
                        <h3>Valor de Refinanciaciones (Con Interés)</h3>
                        <span class="info-value">
                            $<span x-data="{ amount: @js($deudaRefinanciadaInteresTotal) }"
                                     x-text="new Intl.NumberFormat('es-CO').format(amount)"></span>
                        </span>
                    </div>

                    {{-- Total Seguros --}}
                    <div wire:click="abrirModalComisionesRegistradasClick"
                         class="info-item-container cursor-pointer" title="Suma de todos los seguros (comisiones) cobrados en el período seleccionado.">
                        <h3>Total Seguros</h3>
                        <span class="info-value">
                            ${{ number_format($totalComision, 0, ',', '.') }}
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

    {{-- Modal para Transferencia de Dinero en Mano --}}
    @if ($showTransferenciaDineroEnManoModal)
        <div class="fixed inset-0 z-40 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeTransferenciaDineroEnManoModal"></div>

        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
            <div
                class="relative w-full max-w-md overflow-hidden rounded-lg bg-white shadow-xl transition-all dark:bg-gray-800 sm:my-8"
                role="dialog" aria-modal="true" aria-labelledby="modal-transferencia-dinero-mano-headline"
                @click.away="$wire.closeTransferenciaDineroEnManoModal()"
                wire:key="transferencia-dinero-modal-{{ $usuarioSeleccionado?->id ?? 'no-user' }}"
            >
                {{-- Encabezado del Modal --}}
                <div class="border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:px-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white" id="modal-transferencia-dinero-mano-headline">
                            Transferir Dinero Mano <=> Caja
                        </h3>
                        <button
                            type="button"
                            wire:click="closeTransferenciaDineroEnManoModal"
                            class="rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-300"
                        >
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Cuerpo del Modal (Formulario) --}}
                <form wire:submit.prevent="realizarTransferenciaDineroEnMano">
                    <div class="bg-white px-4 pb-4 pt-5 dark:bg-gray-800 sm:p-6 sm:pb-4">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">Usuario: <span class="font-semibold">{{ $usuarioSeleccionado?->name }}</span></p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Ingrese un monto positivo para mover de Mano a Caja, o negativo para mover de Caja a Mano.</p>
                        <div>
                            <label for="montoTransferenciaDineroEnManoInput" class="block text-sm font-medium text-gray-700 dark:text-gray-200 sr-only">Monto a Transferir</label>
                            <input type="number" step="any" id="montoTransferenciaDineroEnManoInput" wire:model.defer="montoTransferenciaDineroEnMano" required
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 sm:text-sm"
                                placeholder="Ej: 20000 o -10000">
                            @error('montoTransferenciaDineroEnMano') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    {{-- Pie del Modal (Botones) --}}
                    <div class="custom-modal-footer"> {{-- Usando la clase de tu adjust-money-modal para consistencia --}}
                        <button type="submit" class="btn-ajustar">Aceptar</button>
                        <button type="button" wire:click="closeTransferenciaDineroEnManoModal" class="btn-cancelar">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
