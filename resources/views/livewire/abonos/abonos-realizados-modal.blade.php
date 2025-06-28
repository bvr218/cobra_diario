<div x-data>
    @if($showModal)
        {{-- Fondo oscuro del modal --}}
        <div class="modal-backdrop" wire:click="cerrarModal">
            {{-- Contenido principal del modal --}}
            <div class="modal-content" @click.stop>
                {{-- Botón de cerrar (la "X") --}}
                <button
                    wire:click="cerrarModal"
                    class="modal-close-button"
                    aria-label="Cerrar modal"
                >
                    &times;
                </button>

                {{-- Título del modal dinámico --}}
                <h2 class="modal-title">
                    {{ $modalTitle }}
                </h2>

                <div class="modal-table-container">
                    @if($abonos->isNotEmpty())
                        <table class="modal-table">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2">Fecha Abono</th>
                                    <th class="px-4 py-2">Cliente</th>
                                    <th class="px-4 py-2">Valor Abonado</th>
                                    <th class="px-4 py-2">Recaudado por</th>
                                    <th class="w-10"></th> {{-- Nueva columna para el botón de eliminar --}}
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($abonos as $abono)
                                    {{-- Añadir clase 'bg-red-200' (o la clase CSS que quieras para el rojo) si el abono está en repeatedRows --}}
                                    <tr
                                        class="cursor-pointer table-row-hoverable {{ in_array($abono->id, $repeatedRows) ? 'background-rojo' : '' }}"
                                    >
                                        <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $abono->prestamo_id }})">{{ $abono->created_at ? Carbon\Carbon::parse($abono->created_at)->format('d/m/Y H:i') : 'N/A' }}</td>
                                        <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $abono->prestamo_id }})">{{ $abono->prestamo->cliente->nombre ?? 'Desconocido' }}</td>
                                        <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $abono->prestamo_id }})">${{ number_format($abono->{$campoValorAbono} ?? 0, 0, ',', '.') }}</td>
                                        <td class="px-4 py-2" wire:click="goToPrestamoEdit({{ $abono->prestamo_id }})">{{ $abono->registradoPor->name ?? 'Desconocido' }}</td> {{-- Usamos la relación 'registradoPor' --}}
                                        <td class="text-center">
                                            {{-- Botón de eliminar --}}
                                            <button
                                                type="button"
                                                wire:click.stop="deleteAbono({{ $abono->id }})"
                                                class="btn-delete-commission"
                                                title="Eliminar abono"
                                            >
                                                X
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="modal-empty-message">No se encontraron abonos para mostrar.</p>
                    @endif
                </div>

                <div class="modal-footer">
                    <button wire:click="cerrarModal" class="modal-close-button-footer">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>