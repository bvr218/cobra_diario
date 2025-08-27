<div x-data x-show="true">
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
                    ×
                </button>

                {{-- Título del modal dinámico --}}
                <h2 class="modal-title">
                    {{ $modalTitle }}
                </h2>

                <div class="modal-table-container">
                    <table class="modal-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-2">Cliente</th>
                                <th class="px-4 py-2">Deuda Inicial</th>
                                <th class="px-4 py-2">Deuda Actual</th>
                                <th class="px-4 py-2">Fecha Último Movimiento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($prestamos as $prestamo)
                                <tr class="table-row-hoverable">
                                    <td class="px-4 py-2">{{ $prestamo->cliente->nombre ?? 'Desconocido' }}</td>
                                    <td class="px-4 py-2">${{ number_format($prestamo->deuda_inicial ?? 0, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2">${{ number_format($prestamo->deuda_actual ?? 0, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2">
                                        @if($prestamo->abonos->isNotEmpty())
                                            {{-- El primer abono es el más reciente gracias al orderBy en la consulta --}}
                                            {{ \Carbon\Carbon::parse($prestamo->abonos->first()->created_at)->format('d/m/Y H:i') }}
                                        @else
                                            {{-- Si no hay abonos, muestra la fecha en que se actualizó (finalizó) el préstamo --}}
                                            {{ \Carbon\Carbon::parse($prestamo->updated_at)->format('d/m/Y H:i') }}
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="modal-empty-message">No se encontraron préstamos finalizados para mostrar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
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