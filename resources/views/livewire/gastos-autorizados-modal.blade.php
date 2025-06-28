{{-- resources/views/livewire/gastos-autorizados-modal.blade.php --}}

<div x-data="{ show: @entangle('showModal').live }" x-show="show" x-cloak>
    @if($showModal)
        {{-- Fondo oscuro del modal --}}
        <div class="modal-backdrop" wire:click="closeModal">
            {{-- Contenido principal del modal --}}
            <div class="modal-content" @click.stop>
                {{-- Botón de cerrar (la "X") --}}
                <button
                    wire:click="closeModal"
                    class="modal-close-button"
                    aria-label="Cerrar modal"
                >
                    &times;
                </button>

                {{-- Título del modal --}}
                <h2 class="modal-title">
                    {{ $modalTitle }}
                </h2>

                <div class="modal-table-container">
                    @if($gastos->isEmpty()) {{-- Cambiado de $gastosAutorizados a $gastos --}}
                        <p class="modal-empty-message">No se encontraron gastos para el período seleccionado.</p>
                    @else
                        <table class="modal-table">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2">Usuario</th>
                                    <th class="px-4 py-2">Valor</th>
                                    <th class="px-4 py-2">Tipo de Gasto</th> {{-- Columna cambiada --}}
                                    <th class="px-4 py-2">Fecha</th>
                                    <th class="px-4 py-2">Autorizado</th> {{-- Nueva columna --}}
                                    <th class="px-4 py-2">Acción</th> {{-- Nueva columna para botón --}}
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($gastos as $gasto) {{-- Cambiado de $gastosAutorizados a $gastos --}}
                                    {{-- Haz la fila clickeable para navegar al recurso de edición del gasto --}}
                                    <tr
                                        class="table-row-hoverable" {{-- Se quita el wire:click general de la fila para que no interfiera con el botón --}}
                                    >
                                        <td class="px-4 py-2 cursor-pointer" wire:click="goToGastoEdit({{ $gasto->id }})">{{ $gasto->user->name ?? 'N/A' }}</td>
                                        <td class="px-4 py-2 cursor-pointer" wire:click="goToGastoEdit({{ $gasto->id }})">${{ number_format($gasto->valor, 0, ',', '.') }}</td>
                                        <td class="px-4 py-2 cursor-pointer" wire:click="goToGastoEdit({{ $gasto->id }})">{{ $gasto->tipoGasto->nombre ?? ($gasto->informacion ?: 'N/A') }}</td> {{-- Muestra tipoGasto o información --}}
                                        <td class="px-4 py-2 cursor-pointer" wire:click="goToGastoEdit({{ $gasto->id }})">{{ $gasto->created_at?->format('d/m/Y H:i') ?? 'N/A' }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full
                                                {{ $gasto->autorizado ? 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100' }}">
                                                {{ $gasto->autorizado ? 'Sí' : 'No' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            @if(!$gasto->autorizado)
                                                <button
                                                    wire:click.stop="autorizarGasto({{ $gasto->id }})"
                                                    class="px-2 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 dark:focus:ring-offset-gray-800 rounded"
                                                >
                                                    Autorizar
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                <div class="modal-footer">
                    <button wire:click="closeModal" class="modal-close-button-footer">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>