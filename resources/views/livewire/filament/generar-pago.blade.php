<div class="gpmod-main-container">
    {{-- Mensajes de alerta --}}
    @if(session()->has('success')) <div class="gpmod-alert gpmod-alert-success">{{ session('success') }}</div> @endif
    @if(session()->has('warning')) <div class="gpmod-alert gpmod-alert-warning">{{ session('warning') }}</div> @endif
    @if(session()->has('error')) <div class="gpmod-alert gpmod-alert-danger">{{ session('error') }}</div> @endif
    @if(session()->has('info')) <div class="gpmod-alert gpmod-alert-info">{{ session('info') }}</div> @endif
    @if(session()->has('success_desc')) <div class="gpmod-alert gpmod-alert-success">{{ session('success_desc') }}</div> @endif
    @if(session()->has('error_desc')) <div class="gpmod-alert gpmod-alert-danger">{{ session('error_desc') }}</div> @endif

    {{-- Card de Filtros Rápidos --}}
    <div class="gpmod-card">
        <div class="gpmod-flex-container gpmod-gap-4">
            <button wire:click="toggleLoanList('vencidos')" class="gpmod-quick-filter-btn {{ $activeLoanListType === 'vencidos' ? 'active' : '' }}">
                Abonos Faltantes
            </button>
            <button wire:click="toggleLoanList('aldia')" class="gpmod-quick-filter-btn {{ $activeLoanListType === 'aldia' ? 'active' : '' }}">
                Abonos Recibidos
            </button>
        </div>
    </div>

    {{-- Modal para la lista de Préstamos --}}
    @if($activeLoanListType)
    <div class="gpmod-modal-overlay" x-data @mousedown.outside="if ($event.target.classList.contains('gpmod-modal-overlay')) { $wire.closeLoanListModal() }">
        <div class="gpmod-modal-content gpmod-loan-list-modal-content-wrapper">
            <div class="gpmod-modal-header">
                <h3>
                    @if($activeLoanListType === 'vencidos') Préstamos Faltantes
                    @elseif($activeLoanListType === 'aldia') Préstamos Recibidos
                    @else Lista de Préstamos @endif
                </h3>
                <button wire:click="closeLoanListModal" type="button" class="gpmod-modal-close-btn">
                    <svg style="width:1.25rem; height:1.25rem;" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                </button>
            </div>
            <div class="gpmod-modal-body">
                @if($loanList->isNotEmpty())
                    <table class="gpmod-loan-list-table">
                        <thead class="gpmod-loan-list-thead">
                            <tr class="gpmod-loan-list-tr">
                                <th class="gpmod-loan-list-th">Cliente</th>
                                <th class="gpmod-loan-list-th">Deuda Inicial</th>
                                <th class="gpmod-loan-list-th">Deuda Actual</th>
                                @if($activeLoanListType !== 'aldia') <th class="gpmod-loan-list-th">Valor Cuota</th> @endif
                                @if($activeLoanListType === 'aldia') 
                                    <th class="gpmod-loan-list-th">Última Cuota Recibida</th>
                                    <th class="gpmod-loan-list-th">Fecha del ultimo Abono Recibido</th>
                                @endif
                                @if($activeLoanListType === 'aldia')
                                    <th class="gpmod-loan-list-th">Proxima fecha de Pago</th>
                                @else
                                    <th class="gpmod-loan-list-th">Fecha de Pago</th>
                                @endif

                            </tr>
                        </thead>
                        <tbody class="gpmod-loan-list-tbody">
                            @foreach($loanList as $item)
                                <tr wire:click="selectLoanFromListAndNavigate({{ $item->id }})" class="gpmod-loan-list-tr">
                                    <td class="gpmod-loan-list-td gpmod-loan-list-td-strong" title="{{ $item->cliente->nombre }}">{{ Str::limit($item->cliente->nombre, 25) }}</td>
                                    <td class="gpmod-loan-list-td">${{ number_format($item->deuda_inicial, 0) }}</td>
                                    <td class="gpmod-loan-list-td">${{ number_format($item->deuda_actual, 0) }}</td>
                                    @if($activeLoanListType !== 'aldia') <td class="gpmod-loan-list-td">${{ number_format($item->monto_por_cuota, 0) }}</td> @endif
                                    @if($activeLoanListType === 'aldia')
                                        @php $ultimoAbono = $item->abonos->sortByDesc('created_at')->first(); @endphp
                                        <td class="gpmod-loan-list-td">{{ $ultimoAbono ? '$' . number_format($ultimoAbono->monto_abono, 0) : 'N/A' }}</td>
                                        <td class="gpmod-loan-list-td">{{ $ultimoAbono ? $ultimoAbono->created_at->format('d/m/Y h:i A') : 'N/A' }}</td>
                                    @endif
                                    <td class="gpmod-loan-list-td">{{ optional($item->next_payment)->format('d/m/Y') ?? 'N/A' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                @if($activeLoanListType !== 'aldia')
                                    <td class="gpmod-loan-list-td" colspan="3"><strong>Total Faltante Por Recoger</strong></td>
                                    <td class="gpmod-loan-list-td"><strong>${{ number_format($totalValorCuota, 0) }}</strong></td>
                                @endif
                                @if($activeLoanListType === 'aldia')
                                    <td class="gpmod-loan-list-td" colspan="3"><strong>Total de los Ultimos Abonos Recogidos</strong></td>
                                    <td class="gpmod-loan-list-td"><strong>${{ number_format($totalUltimaCuota, 0) }}</strong></td>
                                    <td class="gpmod-loan-list-td"></td>
                                @endif
                                <td class="gpmod-loan-list-td"></td>
                            </tr>
                        </tfoot>
                    </table>
                @else
                    <p class="gpmod-text-sm" style="padding: 1rem; text-align:center;">No hay préstamos que coincidan con este filtro.</p>
                @endif
            </div>
            <div class="gpmod-modal-footer">
                <button wire:click="closeLoanListModal" type="button" class="gpmod-btn-secondary">Cerrar</button>
            </div>
        </div>
    </div>
    @endif

    {{-- Card Principal de Pago y Búsqueda --}}
    <div @class([
        'gpmod-card',
        'atraso-nivel-1' => $prestamo?->atraso_nivel === 1,
        'atraso-nivel-2' => $prestamo?->atraso_nivel === 2,
        'atraso-nivel-3' => $prestamo?->atraso_nivel === 3,
    ])>
        <div class="gpmod-search-container">
            <div class="gpmod-flex-container" style="width: 100%; align-items: flex-start; margin-bottom: 0.25rem;">
                <h2>{{ $prestamo->cliente->nombre ?? 'Sin nombre' }}</h2>
                <div class="gpmod-flex-container gpmod-gap-2" style="flex-shrink: 0;">
                    <button class="gpmod-search-icon-btn" wire:click="toggleSearchInput">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1.5rem; height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                    </button>
                    <button class="gpmod-search-icon-btn @if($filterStatus) filter-active @endif" wire:click="toggleFilterInput">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1.5rem; height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L15 13.414V19a1 1 0 01-1.447.894l-4-2A1 1 0 019 17v-3.586L3.293 6.707A1 1 0 013 6V4z" /></svg>
                    </button>
                </div>
            </div>

            @if ($prestamo && $prestamo->cliente)
            <div style="width:100%; margin-top: 0.25rem; margin-bottom: 0.25rem;">
                <div class="gpmod-flex-container gpmod-gap-1">
                    <textarea wire:model.lazy="cliente_descripcion" wire:blur="guardarClienteDescripcion" placeholder="Descripción del cliente..." rows="1" maxlength="255" class="gpmod-textarea-description"></textarea>
                </div>
                @error('cliente_descripcion') <span class="gpmod-validation-error gpmod-text-xs">{{ $message }}</span> @enderror
            </div>
            @endif

            <div class="gpmod-search-filter-wrapper" x-data="{ showSearchInput: @entangle('showSearchInput') }" :class="{ 'active': showSearchInput }" @mousedown.outside="if(showSearchInput) { $wire.toggleSearchInput() }">
                <input type="text" wire:model.live.debounce.350ms="searchTerm" placeholder="Nombre o Cedula" x-ref="searchInput">
                <button class="gpmod-wrapper-close-btn" wire:click="clearSearch">
                     <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1.5rem; height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="gpmod-search-filter-wrapper" x-data="{ showFilterInput: @entangle('showFilterInput') }" :class="{ 'active': showFilterInput }" @mousedown.outside="if(showFilterInput) { $wire.set('showFilterInput', false) }">
                <select wire:model.live="filterStatus" class="gpmod-filter-select">
                    <option value="">Todos los préstamos</option>
                    <option value="vencidos">Préstamos Vencidos</option>
                    <option value="aldia">Préstamos al Día</option>
                </select>
                <button class="gpmod-wrapper-close-btn" wire:click="toggleFilterInput" title="Cerrar filtro">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1.5rem; height:1.5rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>
        </div>

        @if (!$prestamo)
            <div class="gpmod-alert gpmod-alert-warning">No hay préstamos en la ruta.</div>
        @else
            @if($deuda_actual > 0)
                <form wire:submit.prevent="guardar" id="formAbono">
                    <div class="gpmod-flex-container gpmod-gap-4" style="margin-bottom: 1rem;">
                        <div style="flex: 1;"><label>Deuda Inicial</label><input type="text" value="{{ '$'.number_format($deuda_inicial,0) }}" disabled></div>
                        <div style="flex: 1;"><label>Deuda Actual</label><input type="text" value="{{ '$'.number_format($deuda_actual,0) }}" disabled></div>
                    </div>
                    <div class="gpmod-flex-container gpmod-gap-4" style="margin-bottom: 1rem;">
                        <div style="flex: 1; cursor: pointer;" wire:click="toggleInfoFecha" title="Ver fecha de inicio">
                            <label>Fecha de Pago</label><input type="date" value="{{ $next_payment }}" disabled style="pointer-events: none;">
                        </div>
                        <div style="flex: 1;"><label>Cuota</label><input type="text" value="{{ '$'.number_format($monto_por_cuota,0) }}" disabled></div>
                    </div>
                    <div style="margin-bottom: 1rem;">
                        <label class="gpmod-text-sm" style="font-weight: 500; margin-bottom: 0.25rem; display:block;">Último Abono</label>
                        <div class="gpmod-flex-container gpmod-gap-2">
                            <input type="text" value="@if($ultimoAbono){{ '$'.number_format($ultimoAbono->monto_abono, 0) }} ({{ $ultimoAbono->created_at->format('d/m/y H:i') }})@else N/A @endif" disabled class="gpmod-input-grow">
                            @if($prestamo && $prestamo->abonos->isNotEmpty())
                                <button type="button" wire:click="toggleHistorialAbonos" title="Ver historial" class="gpmod-search-icon-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1.75rem; height:1.75rem;"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h7.5M8.25 12h7.5m-7.5 5.25h7.5M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" /></svg>
                                </button>
                            @endif
                        </div>
                    </div>
                    <div>
                        <label for="monto_abono" class="gpmod-text-sm" style="font-weight: 500; margin-bottom: 0.25rem; display:block;">Monto a Abonar</label>
                        <input id="monto_abono" type="number" step="1" wire:model="monto_abono" placeholder="">
                        @error('monto_abono')<span class="gpmod-validation-error">{{ $message }}</span>@enderror
                    </div>
                <div class="gpmod-action-buttons">
                                    @if($this->puede_refinanciar)
                                        <button type="button" wire:click="abrirModalRefinanciamiento" class="gpmod-btn-blue"><span>🔄 Refinanciar Préstamo</span></button>
                                    @endif
                                    
                                        <button type="button" wire:click="iniciarConfirmacion" class="gpmod-btn"><span>💼 Guardar Abono</span></button>
                                    
                                    </div>
                                </form>
                            @else
                                <div class="gpmod-alert gpmod-alert-success" style="text-align:center;">👉 El préstamo está finalizado. No se pueden registrar más pagos. 👈</div>
                            @endif

            <div class="gpmod-flex-between">
                <button class="gpmod-nav-btn" wire:click="prev" @disabled($totalPrestamos === 0)>←</button>
                <div style="text-align:center;">
                    <span>Ruta: {{ $position }}</span>
                    @if($prestamo && $prestamo->cliente)
                        @if($editandoCliente)
                            <form wire:submit.prevent="guardarEdicionCliente" class="gpmod-form-edit-client" novalidate>
                                <div>
                                    <label for="edit_nombre" class="gpmod-text-xs">Nombre</label>
                                    <input id="edit_nombre" type="text" wire:model.defer="edit_cliente_nombre">
                                    @error('edit_cliente_nombre') <span class="gpmod-validation-error gpmod-text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="edit_direccion" class="gpmod-text-xs">Dirección</label>
                                    <input id="edit_direccion" type="text" wire:model.defer="edit_cliente_direccion">
                                    @error('edit_cliente_direccion') <span class="gpmod-validation-error gpmod-text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div>
                                    <label for="edit_telefono" class="gpmod-text-xs">Teléfono</label>
                                    <input id="edit_telefono" type="text" wire:model.defer="edit_cliente_telefono">
                                    @error('edit_cliente_telefono') <span class="gpmod-validation-error gpmod-text-xs">{{ $message }}</span> @enderror
                                </div>
                                <div class="gpmod-form-edit-client-buttons">
                                    <button type="button" wire:click.prevent="cancelarEdicionCliente" class="gpmod-btn-cancelar">Cancelar</button>
                                    <button type="submit" class="gpmod-btn-confirmar">Guardar</button>
                                </div>
                            </form>
                        @else
                            {{-- Se define la variable ANTES para que siempre exista en este scope --}}
                            @php
                                $telefonoUrl = $prestamo->cliente->telefonos[0] ? preg_replace('/[^0-9]/', '', $prestamo->cliente->telefonos[0]) : null;
                                if ($telefonoUrl && !str_starts_with($telefonoUrl, '57')) { $telefonoUrl = '57' . $telefonoUrl; }
                            @endphp

                            <div
                                class="gpmod-client-accordion"
                                x-data="{}"
                                x-init="$watch('$wire.infoDesplegada', value => {
                                    if (value === true) {
                                        $nextTick(() => {
                                            $refs.accordionEnd.scrollIntoView({ behavior: 'smooth', block: 'end' });
                                        });
                                    }
                                })"
                            >
                                {{-- ENCABEZADO DEL ACORDEÓN --}}
                                <div wire:click="toggleInfoDesplegada" class="gpmod-accordion-header">
                                    <p class="gpmod-text-sm" style="font-weight: 600; margin: 0;">
                                        {{ $prestamo->cliente->nombre }}
                                    </p>
                                    <svg class="gpmod-accordion-arrow @if($infoDesplegada) rotated @endif" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                    </svg>
                                </div>

                                {{-- CUERPO DEL ACORDEÓN --}}
                                @if($infoDesplegada)
                                <div class="gpmod-accordion-body">
                                    <div wire:click="toggleEdicionCliente" class="gpmod-client-info-block" title="Clic para editar">
                                        <p class="gpmod-text-xs">CC: {{ $prestamo->cliente->numero_cedula ?? 'N/A' }}</p>
                                        <p class="gpmod-text-xs">Tel: {{ $prestamo->cliente->telefonos[0] ?? 'N/A' }}</p>
                                        <p class="gpmod-text-xs">Dir: {{ $prestamo->cliente->direccion ?? 'N/A' }}</p>
                                    </div>
                                    
                                    {{-- El botón de WhatsApp ahora solo depende de la variable ya definida --}}
                                    @if($telefonoUrl)
                                        <div class="gpmod-whatsapp-wrapper">
                                            <a href="https://wa.me/{{ $telefonoUrl }}" target="_blank" rel="noopener noreferrer" title="Chatear en WhatsApp" class="gpmod-whatsapp-icon">
                                                <img src="{{ asset('storage/icono-whatsapp-sin-fondo.png') }}" alt="WhatsApp" style="width: 40px; height: 40px;">
                                            </a>
                                        </div>
                                    @endif

                                    {{-- ANCLA DE SCROLL: Este div es invisible pero siempre estará aquí para el scroll --}}
                                    <div x-ref="accordionEnd" style="height: 1px; scroll-margin-bottom: 1rem;"></div>
                                </div>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
                <button class="gpmod-nav-btn" wire:click="next" @disabled($totalPrestamos === 0)>→</button>
            </div>
        @endif
    </div>

    {{-- MODALES --}}
    @if($confirmandoAbono)
    <div class="gpmod-modal-overlay" @mousedown.outside="$wire.set('confirmandoAbono', false)">
        <div class="gpmod-modal-content" style="max-width: 400px; text-align:center;">
            <h3>¿Confirmar abono?</h3>
            <p class="gpmod-text-sm">Monto a abonar: <strong>${{ number_format($monto_abono,0) }}</strong></p>
            <p class="gpmod-text-sm">Número de cuota: <strong>{{ $numero_cuota }}</strong></p>
            <p class="gpmod-text-sm">Nuevo saldo: <strong>${{ number_format($nuevo_saldo,0) }}</strong></p>
            <div class="gpmod-modal-buttons">
                <button wire:click="cancelarConfirmacion" class="gpmod-btn-cancelar">Cancelar</button>
                <button wire:click="guardar" class="gpmod-btn-confirmar">Confirmar</button>
            </div>
        </div>
    </div>
    @endif

    @if($refinanciandoPrestamo)
    <div class="gpmod-modal-overlay" @mousedown.outside="$wire.set('refinanciandoPrestamo', false)">
        <div class="gpmod-modal-content" style="max-width: 400px;">
            <h3>Refinanciar Préstamo</h3>
            <form wire:submit.prevent="guardarRefinanciamiento" style="text-align: left;">
                <div style="margin-top: 1rem; margin-bottom: 0.5rem;">
                    <label for="ref_valor_modal" class="gpmod-text-sm">Valor a Refinanciar (COP)</label>
                    <input id="ref_valor_modal" type="number" step="1" wire:model.defer="ref_valor">
                    @error('ref_valor') <span class="gpmod-validation-error gpmod-text-xs">{{ $message }}</span> @enderror
                </div>
                <div style="margin-top: 1rem; margin-bottom: 0.5rem;">
                    <label for="ref_interes_modal" class="gpmod-text-sm">Interés (%)</label>
                    <input id="ref_interes_modal" type="number" step="1" wire:model.defer="ref_interes">
                    @error('ref_interes') <span class="gpmod-validation-error gpmod-text-xs">{{ $message }}</span> @enderror
                </div>
                <div style="margin-top: 1rem; margin-bottom: 0.5rem;">
                    <label for="ref_numero_cuotas_modal" class="gpmod-text-sm">Número de Cuotas</label>
                    <input id="ref_numero_cuotas_modal" type="number" step="1" wire:model.defer="ref_numero_cuotas">
                    @error('ref_numero_cuotas') <span class="gpmod-validation-error gpmod-text-xs">{{ $message }}</span> @enderror
                </div>
                <div style="margin-top: 1rem; margin-bottom: 0.5rem;">
                    <label for="ref_comicion_modal" class="gpmod-text-sm">Valor de Seguro a Cobrar (COP)</label>
                    <input id="ref_comicion_modal" type="number" step="1" wire:model.defer="ref_comicion">
                    @error('ref_comicion') <span class="gpmod-validation-error gpmod-text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="gpmod-modal-buttons">
                    <button type="button" wire:click="cancelarRefinanciamiento" class="gpmod-btn-cancelar">Cancelar</button>
                    <button type="submit" class="gpmod-btn-confirmar">Guardar Refinanciamiento</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if($mostrandoHistorialAbonos && $prestamo)
    <div class="gpmod-modal-overlay" @mousedown.outside="$wire.set('mostrandoHistorialAbonos', false)">
        <div class="gpmod-modal-content" style="max-width: 500px;">
            <div class="gpmod-modal-header">
                <h3>Historial de Abonos</h3>
                <button wire:click="toggleHistorialAbonos" class="gpmod-modal-close-btn">
                    <svg style="width:1.5rem; height:1.5rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div class="gpmod-modal-body">
                @if($historialAbonos->isNotEmpty())
                    <ul style="padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.75rem;">
                        @foreach($historialAbonos as $abonoHistorial)
                            <li class="gpmod-history-item">
                                <div class="gpmod-history-item-amount">Monto: ${{ number_format($abonoHistorial['monto_abono'], 0) }}</div>
                                <div class="gpmod-text-xs">Fecha: {{ $abonoHistorial['created_at']->format('d/m/Y H:i:s') }}</div>
                                <div class="gpmod-text-xs">Cuota N°: {{ $abonoHistorial['numero_cuota_calculado'] }}</div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="gpmod-text-sm">No hay abonos registrados para este préstamo.</p>
                @endif
            </div>
            <div class="gpmod-modal-footer">
                <button wire:click="toggleHistorialAbonos" class="gpmod-btn-cancelar" style="width:100%;">Cerrar</button>
            </div>
        </div>
    </div>
    @endif

    @if($mostrandoInfoFecha && $prestamo)
    <div class="gpmod-modal-overlay" @mousedown.outside="$wire.set('mostrandoInfoFecha', false)">
        <div class="gpmod-modal-content" style="max-width: 400px;">
            <div class="gpmod-modal-header">
                <h3>Fecha de Inicio del Préstamo</h3>
                <button wire:click="toggleInfoFecha" class="gpmod-modal-close-btn">
                    <svg style="width:1.5rem; height:1.5rem;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
            <div style="text-align: center; margin-bottom: 1rem;">
                <p style="font-size: 1.25rem; font-weight: bold;">{{ $fecha_inicio_real }}</p>
                <p class="gpmod-text-xs" style="color: #6b7280; margin-top: 0.25rem;">
                    @if($prestamo->refinanciamientos->isNotEmpty()) (Fecha del último refinanciamiento)
                    @else (Fecha de creación del préstamo original) @endif
                </p>
            </div>
            <div class="gpmod-modal-buttons">
                <button wire:click="toggleInfoFecha" class="gpmod-btn-cancelar" style="width: 100%;">Cerrar</button>
            </div>
        </div>
    </div>
    @endif
</div>