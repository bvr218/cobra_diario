<div>
    @if($showModal)
        <div class="modal-backdrop" wire:click="cerrarModal">
            {{-- Añadimos un estilo para hacer el modal más ancho --}}
            <div class="modal-content" @click.stop style="max-width: 5xl;">
                <button wire:click="cerrarModal" class="modal-close-button no-print">×</button>

                <div class="printable-area">
                    <h2 class="modal-title">{{ $titulo }}</h2>

                    <div class="modal-table-container">
                        <table class="modal-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Ajustado Por</th>
                                    <th>Tipo</th>
                                    <th>Monto Ajuste</th>
                                    <th class="text-left">Descripción</th>
                                    <th class="text-left">Estado Anterior</th>
                                    <th class="text-left">Estado Posterior</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lista as $item)
                                    <tr class="align-top">
                                        <td class="whitespace-nowrap">{{ $item['created_at'] ?? 'N/A' }}</td>
                                        <td>{{ $item['ajustado_por'] ?? 'N/A' }}</td>
                                        <td>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ ($item['tipo_ajuste'] ?? '') === 'positivo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ ucfirst($item['tipo_ajuste'] ?? 'N/A') }}
                                            </span>
                                        </td>
                                        <td class="font-mono text-right whitespace-nowrap">
                                            {{ ($item['tipo_ajuste'] ?? '') === 'positivo' ? '+' : '' }}${{ number_format($item['monto_ajuste'] ?? 0, 0, ',', '.') }}
                                        </td>
                                        <td class="text-sm max-w-xs text-left">{{ $item['descripcion'] ?? 'N/A' }}</td>
                                        <td class="font-mono text-xs text-left">
                                            @foreach($item['dinero_base_antes'] ?? [] as $key => $value)
                                                <div>
                                                    {{-- Usamos el array $columnLabels para obtener la etiqueta. Si no existe, muestra la clave original. --}}
                                                    <span class="font-semibold">{{ $columnLabels[$key] ?? $key }}:</span>
                                                    ${{ number_format($value, 0, ',', '.') }}
                                                </div>
                                            @endforeach
                                        </td>
                                        <td class="font-mono text-xs text-left">
                                             @foreach($item['dinero_base_despues'] ?? [] as $key => $value)
                                                <div>
                                                    <span class="font-semibold">{{ $columnLabels[$key] ?? $key }}:</span>
                                                    ${{ number_format($value, 0, ',', '.') }}
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="modal-empty-message">No se encontraron ajustes de dinero en esta liquidación.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer no-print">
                    <button wire:click="cerrarModal" class="modal-close-button-footer">Cerrar</button>
                </div>
            </div>
        </div>
    @endif
</div>