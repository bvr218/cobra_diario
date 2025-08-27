<div>
    @if($showModal)
        <div class="modal-backdrop" wire:click="cerrarModal">
            <div class="modal-content" @click.stop style="max-width: 4xl;"> {{-- Un modal más ancho --}}
                <button wire:click="cerrarModal" class="modal-close-button">&times;</button>
                <h2 class="modal-title">{{ $modalTitle }}</h2>

                <div class="modal-table-container">
                    @if($ajustes->isNotEmpty())
                        <table class="modal-table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Ajustado Por</th>
                                    <th>Tipo</th>
                                    <th>Monto Ajuste</th>
                                    <th>Descripción</th>
                                    <th>Estado Anterior</th>
                                    <th>Estado Posterior</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ajustes as $ajuste)
                                    <tr class="align-top">
                                        <td class="whitespace-nowrap">{{ \Carbon\Carbon::parse($ajuste->created_at)->format('d/m/Y H:i') }}</td>
                                        <td>{{ $ajuste->ajustadoPor->name ?? 'N/A' }}</td>
                                        <td>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $ajuste->tipo_ajuste === 'positivo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                {{ ucfirst($ajuste->tipo_ajuste) }}
                                            </span>
                                        </td>
                                        <td class="font-mono text-right whitespace-nowrap">{{ $ajuste->tipo_ajuste === 'positivo' ? '+' : '' }}${{ number_format($ajuste->monto_ajuste, 0, ',', '.') }}</td>
                                        <td class="text-sm max-w-xs">{{ $ajuste->descripcion }}</td>
                                        <td class="font-mono text-xs text-left">
                                            @foreach($ajuste->dinero_base_antes as $key => $value)
                                                <div>
                                                    {{-- Usamos el array $columnLabels para obtener la etiqueta --}}
                                                    <span class="font-semibold">{{ $columnLabels[$key] ?? str_replace('_', ' ', $key) }}:</span>
                                                    ${{ number_format($value, 0, ',', '.') }}
                                                </div>
                                            @endforeach
                                        </td>
                                        <td class="font-mono text-xs text-left">
                                             @foreach($ajuste->dinero_base_despues as $key => $value)
                                                <div>
                                                    <span class="font-semibold">{{ $columnLabels[$key] ?? str_replace('_', ' ', $key) }}:</span>
                                                    ${{ number_format($value, 0, ',', '.') }}
                                                </div>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="modal-empty-message">No se encontraron ajustes para el período seleccionado.</p>
                    @endif
                </div>

                <div class="modal-footer">
                    <button wire:click="cerrarModal" class="modal-close-button-footer">Cerrar</button>
                </div>
            </div>
        </div>
    @endif
</div>