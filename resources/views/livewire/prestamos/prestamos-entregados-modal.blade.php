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
                    &times;
                </button>

                {{-- Título del modal dinámico --}}
                <h2 class="modal-title">
                    {{ $modalTitle }} {{-- ¡Ahora el título es completamente dinámico! --}}
                </h2>

                <div class="modal-table-container">
                    <table class="modal-table">
                        <thead>
                            <tr>
                                <th class="px-4 py-2">Posición en la Ruta</th>
                                <th class="px-4 py-2">Cliente</th>
                                <th class="px-4 py-2">Valor Prestado</th> {{-- Este encabezado será estático --}}
                                <th class="px-4 py-2">Estado</th>
                                <th class="px-4 py-2">Fecha Creación</th>
                                <th class="px-4 py-2">Descripción</th>
                                @if (str_starts_with($modalTitle, 'Préstamos Entregados'))
                                    <th class="px-4 py-2">Acción</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($prestamos as $prestamo)
                                <tr
                                    {{-- ELIMINA ESTA CONDICIÓN PARA HACER TODAS LAS FILAS CLICABLES --}}
                                    {{-- @if (!(str_starts_with($modalTitle, 'Préstamos Entregados') && $prestamo->estado === 'pendiente')) --}}
                                        wire:click="goToPrestamoEdit({{ $prestamo->id }})"
                                    {{-- @endif --}}
                                    class="cursor-pointer table-row-hoverable"
                                >
                                    <td class="px-4 py-2">{{ $prestamo->posicion_ruta ?? 0 }}</td>
                                    <td class="px-4 py-2">{{ $prestamo->cliente->nombre ?? 'Desconocido' }}</td>
                                    {{-- Aquí está el cambio clave: Usamos la propiedad $valorCampo --}}
                                    <td class="px-4 py-2">${{ number_format($prestamo->{$valorCampo} ?? 0, 0, ',', '.') }}</td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full
                                            {{ $prestamo->estado === 'autorizado' ? 'bg-blue-100 text-blue-800 dark:bg-blue-700 dark:text-blue-100' :
                                               ($prestamo->estado === 'activo' ? 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100' :
                                               ($prestamo->estado === 'pendiente' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100' :
                                               ($prestamo->estado === 'pagado' ? 'bg-purple-100 text-purple-800 dark:bg-purple-700 dark:text-purple-100' :
                                                                                            'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-100'))) }}">
                                                {{ ucfirst($prestamo->estado) }}
                                            </span>
                                    </td>
                                    <td class="px-4 py-2">{{ $prestamo->created_at ? Carbon\Carbon::parse($prestamo->created_at)->format('d/m/Y') : 'N/A' }}</td>
                                    <td class="px-4 py-2">{{ $prestamo->cliente->descripcion ?? 'Sin Descripción' }}</td>
                                    @if (str_starts_with($modalTitle, 'Préstamos Entregados'))
                                        <td class="px-4 py-2 text-center">
                                            @if($prestamo->estado === 'pendiente')
                                                <button
                                                    wire:click.stop="autorizarPrestamo({{ $prestamo->id }})"
                                                    class="px-2 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 hover:underline focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-1 dark:focus:ring-offset-gray-800 rounded"
                                                >
                                                    Autorizar
                                                </button>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                @php
                                    $colspan = str_starts_with($modalTitle, 'Préstamos Entregados') ? 7 : 6;
                                @endphp
                                <tr>
                                    <td colspan="{{ $colspan }}" class="modal-empty-message">No se encontraron préstamos para mostrar.</td>
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