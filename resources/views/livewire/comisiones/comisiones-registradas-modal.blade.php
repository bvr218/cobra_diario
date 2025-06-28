<div x-data="{ show: @entangle('showModal') }" x-show="show" class="modal-backdrop" style="display: none;">
    <div class="modal-content">
        <h3 class="modal-title">
            Información de Seguros Cobrados
            @if ($agente)
                por {{ $agente->name ?? 'Usuario Desconocido' }}
            @endif
        </h3>

        <button type="button" wire:click="cerrarModal" class="modal-close-button">
            &times;
        </button>

        <div class="modal-table-container">
            @if ($comisiones->isNotEmpty())
                <table class="modal-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Origen</th> {{-- Nueva columna para el origen --}}
                            <th>Cliente</th>
                            <th>Monto</th>
                            <th class="w-10"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($comisiones as $comision)
                            @php
                                $prestamoUrl = $comision['prestamo_id']
                                    ? \App\Filament\Resources\PrestamoResource::getUrl('edit', ['record' => $comision['prestamo_id']])
                                    : '#'; // En caso de que no haya prestamo_id para el enlace
                            @endphp
                            <tr
                                onclick="window.location='{{ $prestamoUrl }}';"
                                class="cursor-pointer table-row-hoverable"
                            >
                                <td>{{ $comision['fecha'] }}</td>
                                <td>{{ $comision['origen'] }}</td> {{-- Muestra el origen aquí --}}
                                <td>{{ $comision['cliente_nombre'] }}</td>
                                <td>${{ number_format($comision['monto'], 0, ',', '.') }}</td>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        wire:click.stop="deleteCommission({{ $comision['id'] }}, '{{ $comision['origen'] }}')" {{-- Pasa el ID y el Origen --}}
                                        class="btn-delete-commission"
                                        title="Eliminar comisión"
                                    >
                                        X
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="modal-empty-message">No se encontraron comisiones para el rango de fechas seleccionado.</p>
            @endif
        </div>
    </div>
</div>
