{{-- resources/views/livewire/refinanciaciones/refinanciaciones-modal.blade.php --}}

<div x-data="{ show: @entangle('showModal').live }" x-show="show" x-cloak>
    @if($showModal)
        {{-- Fondo oscuro del modal --}}
        <div class="modal-backdrop" wire:click="closeModal">
            {{-- Contenido principal del modal --}}
            <div class="modal-content" @click.stop>
                {{-- Botón de cerrar --}}
                <button
                    wire:click="closeModal"
                    class="modal-close-button"
                    aria-label="Cerrar modal"
                >
                    &times;
                </button>

                {{-- Título dinámico --}}
                <h2 class="modal-title">
                    {{ $modalTitle }}
                </h2>

                <div class="modal-table-container">
                    @if($refinanciaciones->isEmpty())
                        <p class="modal-empty-message">
                            No se encontraron refinanciaciones para el período seleccionado.
                        </p>
                    @else
                        <table class="modal-table w-full">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2">Cliente</th>
                                    <th class="px-4 py-2">Fecha Refinanciamiento</th>

                                    {{-- **Nueva columna solo en cantidad** --}}
                                    @if($modalType === 'cantidad')
                                        <th class="px-4 py-2">Deuda Inicial</th>
                                    @endif

                                    {{-- **Nueva columna solo en cantidad** --}}
                                    @if($modalType === 'cantidad')
                                        <th class="px-4 py-2">Deuda Antes de Refinanciar</th>
                                    @endif

                                    {{-- Valor total --}}
                                    @if($modalType === 'valor_total')
                                        <th class="px-4 py-2">Deuda sin Interés</th>
                                    @endif

                                    {{-- Común cantidad y valor_total --}}
                                    @if($modalType === 'cantidad' || $modalType === 'valor_total')
                                        <th class="px-4 py-2">Valor entregado</th>
                                    @endif

                                    {{-- Valor con interés --}}
                                    @if($modalType === 'valor_interes')
                                        <th class="px-4 py-2">Deuda Con Interés</th>
                                    @endif

                                    {{-- Deuda tras refinanciar (cantidad) --}}
                                    @if($modalType === 'cantidad')
                                        <th class="px-4 py-2">Deuda tras Refinanciar</th>
                                    @endif

                                    {{-- Estado y Acción (solo cantidad) --}}
                                    @if($modalType === 'cantidad')
                                        <th class="px-4 py-2">Estado</th>
                                        <th class="px-4 py-2">Acción</th>
                                    @endif

                                    {{-- Columna eliminar --}}
                                    <th class="w-10"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($refinanciaciones as $refinanciacion)
                                    <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $refinanciacion->prestamo_id }})">
                                            {{ $refinanciacion->prestamo->cliente->nombre }}
                                        </td>
                                        <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $refinanciacion->prestamo_id }})">
                                            {{ $refinanciacion->created_at->format('d/m/Y H:i') }}
                                        </td>

                                        {{-- **Deuda Inicial** --}}
                                        @if($modalType === 'cantidad')
                                            <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $refinanciacion->prestamo_id }})">
                                                ${{ number_format($refinanciacion->prestamo->deuda_inicial ?? 0, 0, ',', '.') }}
                                            </td>
                                        @endif

                                        {{-- **Préstamo Antes de Refinanciar** --}}
                                        @if($modalType === 'cantidad')
                                            <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $refinanciacion->prestamo_id }})">
                                                ${{ number_format($refinanciacion->deuda_anterior ?? 0, 0, ',', '.') }}
                                            </td>
                                        @endif

                                        {{-- Valor total --}}
                                        @if($modalType === 'valor_total')
                                            <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $refinanciacion->prestamo_id }})">
                                                ${{ number_format($refinanciacion->deuda_refinanciada, 0, ',', '.') }}
                                            </td>
                                        @endif

                                        {{-- Común cantidad y valor_total --}}
                                        @if($modalType === 'cantidad' || $modalType === 'valor_total')
                                            <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $refinanciacion->prestamo_id }})">
                                                ${{ number_format($refinanciacion->valor, 0, ',', '.') }}
                                            </td>
                                        @endif

                                        {{-- Valor con interés --}}
                                        @if($modalType === 'valor_interes')
                                            <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $refinanciacion->prestamo_id }})">
                                                ${{ number_format($refinanciacion->deuda_refinanciada_interes, 0, ',', '.') }}
                                            </td>
                                        @endif

                                        {{-- Deuda tras refinanciar --}}
                                        @if($modalType === 'cantidad')
                                            <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $refinanciacion->prestamo_id }})">
                                                ${{ number_format($refinanciacion->deuda_refinanciada_interes, 0, ',', '.') }}
                                            </td>
                                        @endif

                                        {{-- Estado --}}
                                        @if($modalType === 'cantidad')
                                            <td class="px-4 py-2">
                                                <span
                                                    class="px-2 py-1 text-xs font-semibold rounded-full
                                                        {{ $refinanciacion->estado === 'autorizado'
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100'
                                                            : ($refinanciacion->estado === 'pendiente'
                                                                ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100'
                                                                : 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100') }}"
                                                >
                                                    {{ ucfirst($refinanciacion->estado) }}
                                                </span>
                                            </td>
                                        @endif

                                        {{-- Acción Autorizar --}}
                                        @if($modalType === 'cantidad')
                                            <td class="px-4 py-2 text-center">
                                                @if($refinanciacion->estado === 'pendiente')
                                                    <button
                                                        wire:click.stop="autorizarRefinanciamiento({{ $refinanciacion->id }})"
                                                        class="px-2 py-1 text-xs font-medium underline rounded focus:outline-none focus:ring-2 focus:ring-blue-500"
                                                    >
                                                        Autorizar
                                                    </button>
                                                @endif
                                            </td>
                                        @endif

                                        {{-- Eliminar --}}
                                        <td class="px-4 py-2 text-center">
                                            <button
                                                type="button"
                                                wire:click.stop="deleteRefinanciamiento({{ $refinanciacion->id }})"
                                                class="btn-delete-commission"
                                                title="Eliminar refinanciación"
                                            >
                                                ×
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                {{-- Footer --}}
                <div class="modal-footer">
                    <button wire:click="closeModal" class="modal-close-button-footer">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
