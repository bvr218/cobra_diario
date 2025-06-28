<div class="generarPago-container">
    {{-- Mensajes de éxito/error --}}
    @if(session()->has('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session()->has('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif
    @if(session()->has('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if(session()->has('info'))
        <div class="alert alert-info">{{ session('info') }}</div>
    @endif
    @if(session()->has('success_desc'))
        <div class="alert alert-success">{{ session('success_desc') }}</div>
    @endif
    @if(session()->has('error_desc'))
        <div class="alert alert-danger">{{ session('error_desc') }}</div>
    @endif

    {{-- Nuevo contenedor para Préstamos Vencidos/Al Día --}}
    {{-- Card for Quick Filters --}}
    <div class="quick-filters-card">
        <div class="flex gap-4">
            <button wire:click="toggleLoanList('vencidos')"
                    class="quick-filter-btn {{ $activeLoanListType === 'vencidos' ? 'active' : '' }}">
                Abonos Faltantes
            </button>
            <button wire:click="toggleLoanList('aldia')"
                    class="quick-filter-btn {{ $activeLoanListType === 'aldia' ? 'active' : '' }}">
                Abonos Recibidos
            </button>
        </div>
    </div>

    {{-- Modal para la lista de Préstamos Vencidos/Al Día --}}
    @if($activeLoanListType)
    <div class="fixed inset-0 bg-gray-900 bg-opacity-50 dark:bg-opacity-80 flex items-center justify-center z-50"
         x-data @mousedown.outside="if ($event.target.classList.contains('fixed')) { $wire.closeLoanListModal() }">
        <div class="gp-loan-list-modal-content-wrapper">
            {{-- Encabezado del Modal --}}
            <div class="gp-loan-list-modal-header">
                <h3>
                    @if($activeLoanListType === 'vencidos')
                        Préstamos Vencidos
                    @elseif($activeLoanListType === 'aldia')
                        Préstamos al Día
                    @else
                        Lista de Préstamos
                    @endif
                </h3>
                <button wire:click="closeLoanListModal" type="button"
                        class="gp-loan-list-modal-close-btn">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                </button>
            </div>

            {{-- Cuerpo del Modal (Tabla) --}}
            <div class="gp-loan-list-modal-body">
                @if($loanList->isNotEmpty())
                    <table class="gp-loan-list-modal-table">
                        <thead class="gp-loan-list-modal-table-header">
                            <tr class="gp-loan-list-modal-table-row">
                                <th class="gp-loan-list-modal-table-cell">Cliente</th>
                                <th class="gp-loan-list-modal-table-cell">Deuda Inicial</th>
                                <th class="gp-loan-list-modal-table-cell">Deuda Actual</th>
                                <th class="gp-loan-list-modal-table-cell">Fecha de Pago</th>
                            </tr>
                        </thead>
                        <tbody class="gp-loan-list-modal-table-body">
                            @foreach($loanList as $item)
                                <tr wire:click="selectLoanFromListAndNavigate({{ $item->id }})" class="gp-loan-list-modal-table-row gp-loan-list-modal-table-row-hover">
                                    <td class="gp-loan-list-modal-table-cell font-medium whitespace-nowrap truncate" title="{{ $item->cliente->nombre }}">{{ $item->cliente->nombre }}</td>
                                    <td class="gp-loan-list-modal-table-cell">${{ number_format($item->deuda_inicial, 0) }}</td>
                                    <td class="gp-loan-list-modal-table-cell">${{ number_format($item->deuda_actual, 0) }}</td>
                                    <td class="gp-loan-list-modal-table-cell">{{ optional($item->next_payment)->format('d/m/Y') ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-sm p-3 text-center">No hay préstamos que coincidan con este filtro.</p>
                @endif
            </div>

            {{-- Pie del Modal --}}
            <div class="gp-loan-list-modal-footer">
                <button wire:click="closeLoanListModal" type="button" class="btn-secondary">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Card for Search and Payment Form --}}
    <div class="payment-details-card">
        <div class="search-container">
            {{-- Contenedor para el nombre y los iconos --}}
            <div class="flex items-start mb-1">
                <h2 style="margin: 0; margin-right: 0.5rem; flex-grow: 1; min-width: 0;">
                    {{ $prestamo->cliente->nombre ?? 'Sin nombre' }}
                </h2>
                <div class="flex items-center space-x-2 flex-shrink-0"> {{-- Contenedor para los iconos --}}
                    <button class="search-icon-btn" wire:click="toggleSearchInput">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </button>
                    <button class="search-icon-btn @if($filterStatus) filter-active @endif" wire:click="toggleFilterInput">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="24" height="24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L15 13.414V19a1 1 0 01-1.447.894l-4-2A1 1 0 019 17v-3.586L3.293 6.707A1 1 0 013 6V4z" />
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Input para la descripción del cliente (ahora debajo del bloque nombre/iconos) --}}
            @if ($prestamo && $prestamo->cliente)
            {{-- Reducido el margen inferior de mb-2 a mb-1 --}}
            <div class="mt-1 mb-1 w-full">
                <div class="flex items-start space-x-1">
                    <textarea wire:model.lazy="cliente_descripcion"
                              wire:blur="guardarClienteDescripcion"
                              placeholder="Descripción del cliente..."
                              rows="1"
                              maxlength="255"
                              class="p-1.5 text-xs"></textarea> {{-- Tailwind p-1.5 text-xs for specific sizing --}}
                </div>
                @error('cliente_descripcion') <span class="validation-error text-xs">{{ $message }}</span> @enderror
            </div>
            @endif

            {{-- MODIFICACIÓN AQUÍ --}}
            <div class="search-input-wrapper"
                 x-data="{ showSearchInput: @entangle('showSearchInput') }"
                 :class="{ 'active': showSearchInput }"
                 @mousedown.outside="if(showSearchInput) { $wire.toggleSearchInput() }"
            >
                <input type="text" wire:model.live.debounce.350ms="searchTerm" placeholder="Nombre o Cedula" class="search-input" x-ref="searchInput" x-init="$watch('showSearchInput', value => { if (value) { $refs.searchInput.focus() } })">
                <button class="clear-search-btn" wire:click="clearSearch">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            {{-- Nuevo select de filtro --}}
            <div class="filter-select-wrapper"
                 x-data="{ showFilterInput: @entangle('showFilterInput') }"
                 :class="{ 'active': showFilterInput }"
                 @mousedown.outside="if(showFilterInput) { $wire.set('showFilterInput', false) }"
            >
                <select wire:model.live="filterStatus" class="filter-select">
                    <option value="">Todos los préstamos</option>
                    <option value="vencidos">Préstamos Vencidos</option>
                    <option value="aldia">Préstamos al Día</option>
                </select>
                {{-- Botón para cerrar el modal del filtro (la 'X'), siempre visible --}}
                <button class="clear-filter-btn" wire:click="toggleFilterInput" title="Cerrar filtro">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" width="24" height="24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        @if (!$prestamo)
            <div class="alert alert-warning">No hay préstamos en la ruta.</div>
        @else
            {{-- Si todavía hay deuda: mostramos el formulario --}}
            @if($deuda_actual > 0)
                <form wire:submit.prevent="guardar" id="formAbono">
                    {{-- Deuda Inicial / Deuda Actual --}}
                    <div class="form-row-flex">
                        <div class="form-col-flex">
                            <label>Deuda Inicial</label>
                            <input type="text" value="{{ '$'.number_format($deuda_inicial,0) }}" disabled>
                        </div>
                        <div class="form-col-flex">
                            <label>Deuda Actual</label>
                            <input type="text" value="{{ '$'.number_format($deuda_actual,0) }}" disabled>
                        </div>
                    </div>

                    {{-- Fecha de Pago / Valor de Cuota --}}
                    <div class="form-row-flex">
                        <div class="form-col-flex">
                            <label>Fecha de Pago</label>
                            <input type="date" value="{{ $next_payment }}" disabled>
                        </div>
                        <div class="form-col-flex">
                            <label>Cuota</label>
                            <input type="text" value="{{ '$'.number_format($monto_por_cuota,0) }}" disabled>
                        </div>
                    </div>

                    {{-- Último Abono --}}
                    <div class="mb-3">
                        <label class="block text-sm font-medium mb-1">Último Abono</label>
                        {{-- Contenedor flex para alinear input e icono en la misma línea --}}
                        <div class="flex items-center space-x-2">
                            {{-- Input ocupa el espacio disponible --}}
                            <input id="ultimo_abono_display_field" type="text"
                                   value="@if($ultimoAbono){{ '$'.number_format($ultimoAbono->monto_abono, 0) }} ({{ $ultimoAbono->created_at->format('d/m/y H:i') }})@else N/A @endif"
                                   disabled
                                   class="p-2 flex-grow"> {{-- Tailwind p-2 flex-grow for specific sizing/layout --}}
                            {{-- Botón del icono, ya no es absoluto --}}
                            @if($prestamo && $prestamo->abonos->isNotEmpty())
                                <button type="button" wire:click="toggleHistorialAbonos" title="Ver historial de abonos"
                                        class="search-icon-btn historial-btn"> {{-- Re-used search-icon-btn for base style, added historial-btn for specifics --}}
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h7.5M8.25 12h7.5m-7.5 5.25h7.5M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
                                    </svg>
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Monto a Abonar --}}
                    <div>
                        <label for="monto_abono" class="block text-sm font-medium mb-1">Monto a Abonar</label>
                        <input id="monto_abono" type="number" step="1" wire:model="monto_abono" placeholder="">
                        @error('monto_abono')
                            <span class="validation-error">{{ $message }}</span>
                        @enderror
                    </div>

                    {{-- Botón que dispara el método público iniciarConfirmacion --}}
                    <button type="button" wire:click="iniciarConfirmacion" class="btn-guardar w-full mt-4">
                        💼 Guardar Abono
                    </button>
                </form>
            @else
                {{-- Cuando deuda_actual ≤ 0 --}}
                <div class="alert alert-success text-center">
                    👉 El préstamo está finalizado. No se pueden registrar más pagos. 👈
                </div>
            @endif
            
            {{-- Botón para abrir modal de refinanciamiento --}}
            @if($this->puede_refinanciar)
                <button type="button" wire:click="abrirModalRefinanciamiento" class="btn-blue w-full mt-3">
                    🔄 Refinanciar Préstamo
                </button>
            @endif


            {{-- Navegación entre préstamos --}}
            <div class="flex-between mt-4">
                <button class="nav-btn" wire:click="prev" @disabled($totalPrestamos === 0)>
                    ←
                </button>
                <div class="text-center">
                    <span>Ruta: {{ $position }}</span>
                    @if($prestamo && $prestamo->cliente)
                        @php 
                            $telefonoRaw = $prestamo->cliente->telefonos[0] ?? null;
                            $telefono = $telefonoRaw ? preg_replace('/[^0-9]/', '', $telefonoRaw) : null;
                            if ($telefono && !str_starts_with($telefono, '57')) {
                                $telefono = '57' . $telefono;
                            }
                            // Limpiamos el número de teléfono para la URL de WhatsApp, eliminando caracteres no numéricos.
                            $telefonoUrl = $telefono ? preg_replace('/[^0-9]/', '', $telefono) : null;
                        @endphp

                        <a @if($telefonoUrl) href="https://wa.me/{{ $telefonoUrl }}" target="_blank" rel="noopener noreferrer" title="Chatear en WhatsApp" @endif
                           class="block p-1 rounded-md @if($telefonoUrl) hover:bg-gray-100 dark:hover:bg-gray-800 cursor-pointer @else cursor-default @endif">
                            <p class="text-sm font-semibold">{{ $prestamo->cliente->nombre }}</p>
                            <p class="text-xs">CC: {{ $prestamo->cliente->numero_cedula ?? 'N/A' }}</p>
                            <p class="text-xs">Tel: {{ $telefonoRaw ?? 'N/A' }}</p>
                        </a>
                    @endif
                </div>
                <button class="nav-btn" wire:click="next" @disabled($totalPrestamos === 0)>
                    →
                </button>
            </div>
        @endif
    </div> {{-- End of payment-details-card --}}

    {{-- ============================================= --}}
    {{-- MODAL DE CONFIRMACIÓN DE ABONO (gestionado por Livewire) --}}
    {{-- ============================================= --}}
    @if($confirmandoAbono)
        <div class="modal-overlay" style="display: flex;">
            <div class="modal">
                <h3>¿Confirmar abono?</h3>
                <p class="text-sm">Monto a abonar: <strong>${{ number_format($monto_abono,0) }}</strong></p>
                <p class="text-sm">Número de cuota: <strong>{{ $numero_cuota }}</strong></p>
                <p class="text-sm">Nuevo saldo: <strong>${{ number_format($nuevo_saldo,0) }}</strong></p>
                <div class="modal-buttons">
                    <button wire:click="cancelarConfirmacion" class="btn-cancelar">
                        Cancelar
                    </button>
                    <button wire:click="guardar" class="btn-confirmar">
                        Confirmar
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ============================================= --}}
    {{-- MODAL DE REFINANCIAMIENTO --}}
    {{-- ============================================= --}}
    @if($refinanciandoPrestamo)
        <div class="modal-overlay" style="display: flex;">
            <div class="modal">
                <h3>Refinanciar Préstamo</h3>
                <form wire:submit.prevent="guardarRefinanciamiento">
                    <div class="mt-4 mb-2 text-left">
                        <label for="ref_valor_modal" class="block text-sm font-medium mb-1">
                            Valor a Refinanciar (COP)
                        </label>
                        <input id="ref_valor_modal" type="number" step="1" wire:model.defer="ref_valor" placeholder="">
                        @error('ref_valor') <span class="validation-error text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="mt-4 mb-2 text-left">
                        <label for="ref_interes_modal" class="block text-sm font-medium mb-1">
                            Interés (%)
                        </label>
                        <input id="ref_interes_modal" type="number" step="1" wire:model.defer="ref_interes" placeholder="">
                        @error('ref_interes') <span class="validation-error text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="mt-4 mb-2 text-left">
                        <label for="ref_comicion_modal" class="block text-sm font-medium mb-1">
                            Valor de Seguro a Cobrar (COP)
                        </label>
                        <input id="ref_comicion_modal" type="number" step="1" wire:model.defer="ref_comicion" placeholder="">
                        @error('ref_comicion') <span class="validation-error text-xs">{{ $message }}</span> @enderror
                    </div>

                    <div class="modal-buttons">
                        <button type="button" wire:click="cancelarRefinanciamiento" class="btn-cancelar">Cancelar</button>
                        <button type="submit" class="btn-confirmar">Guardar Refinanciamiento</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ============================================= --}}
    {{-- MODAL HISTORIAL DE ABONOS --}}
    {{-- ============================================= --}}
    @if($mostrandoHistorialAbonos && $prestamo)
        <div class="modal-overlay" style="display: flex;" x-data @mousedown.outside="$wire.set('mostrandoHistorialAbonos', false)">
            {{-- Added display:flex, flex-direction:column and max-height for the modal itself --}}
            <div class="modal" style="max-width: 500px; display: flex; flex-direction: column; max-height: 85vh;">
                {{-- Header part, set to not shrink --}}
                <div class="flex justify-between items-center mb-4 flex-shrink-0">
                    <h3>Historial de Abonos</h3>
                    <button wire:click="toggleHistorialAbonos" class="modal-close-btn" style="margin-left: auto;"> {{-- Added style for positioning --}}
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                
                {{-- Content part, set to grow and scroll --}}
                <div class="overflow-y-auto flex-grow mb-4 pr-1"> {{-- Added pr-1 for scrollbar space --}}
                    @if($historialAbonos->isNotEmpty())
                        <ul class="history-list">
                            @foreach($historialAbonos as $abonoHistorial)
                                <li class="history-list-item">
                                    <div class="font-semibold">Monto: ${{ number_format($abonoHistorial->monto_abono, 0) }}</div>
                                    <div class="text-xs">Fecha: {{ $abonoHistorial->created_at->format('d/m/Y H:i:s') }}</div>
                                    <div class="text-xs">Cuota N°: {{ $abonoHistorial->numero_cuota }}</div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-sm">No hay abonos registrados para este préstamo.</p>
                    @endif
                </div>

                {{-- Footer part, set to not shrink and pushed to bottom if content is short --}}
                <div class="modal-buttons mt-auto flex-shrink-0">
                    <button wire:click="toggleHistorialAbonos" class="btn-cancelar w-full">Cerrar</button>
                </div>
            </div>
        </div>
    @endif
</div>
