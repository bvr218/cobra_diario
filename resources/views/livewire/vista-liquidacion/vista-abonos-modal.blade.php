<div>
    @if($showModal)
        <div class="modal-backdrop" wire:click="cerrarModal">
            <div class="modal-content" @click.stop x-data="{ print() { window.print(); } }">
                <button wire:click="cerrarModal" class="modal-close-button no-print">×</button>

                <div id="printable-abonos-content" class="printable-area">
                    <h2 class="modal-title">{{ $titulo }}</h2>

                    <!-- BOTONES DE ACCIÓN (NUEVA UBICACIÓN) -->
                    <div class="modal-actions-header no-print">
                        @if($selectedAgentId && $selectedYear && $selectedMonth)
                            <a href="{{ route('liquidacion.detalle.pdf', ['tipo' => $tipoLista, 'agentId' => $selectedAgentId, 'year' => $selectedYear, 'month' => $selectedMonth]) }}"
                               target="_blank" class="action-button pdf-button">
                               <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V4a2 2 0 00-2-2H5zm0 2h10v7.5a.5.5 0 01-.5.5H5.5a.5.5 0 01-.5-.5V4zM5.5 13H12v1.5a.5.5 0 01-.5.5h-5a.5.5 0 01-.5-.5V13z" clip-rule="evenodd" /><path d="M13 8.5a.5.5 0 00-.5-.5H7.5a.5.5 0 000 1H12a.5.5 0 00.5-.5z" /></svg>
                                Exportar a PDF
                            </a>
                        @endif
                        <!-- <button type="button" @click="print()" class="action-button print-button">
                           <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v2H2a2 2 0 00-2 2v8a2 2 0 002 2h1v-2H2a.5.5 0 01-.5-.5V8a.5.5 0 01.5-.5h14a.5.5 0 01.5.5v5.5a.5.5 0 01-.5-.5H17v2h1a2 2 0 002-2V8a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H5zm10 2H5v2h10V4zM4 9a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm1 3a1 1 0 100 2h8a1 1 0 100-2H5z" clip-rule="evenodd" /></svg>
                           &nbsp;Imprimir
                        </button> -->
                    </div>

                    <div class="modal-table-container">
                        <table class="modal-table">
                            <thead>
                                <tr>
                                    <th>Fecha Abono</th>
                                    <th>Cliente</th>
                                    <th>Valor Abonado</th>
                                    <th>Recaudado por</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($lista as $item)
                                    <tr class="table-row-hoverable {{ ($item['is_repeated'] ?? false) ? 'background-rojo' : '' }}">
                                        <td>{{ $item['created_at'] ?? 'N/A' }}</td>
                                        <td>{{ $item['cliente_nombre'] ?? 'N/A' }}</td>
                                        <td>${{ number_format($item['monto_abono'] ?? 0, 0, ',', '.') }}</td>
                                        <td>{{ $item['recaudado_por'] ?? 'N/A' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="modal-empty-message">No se encontraron abonos para mostrar.</td></tr>
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

        @once
        <style>
            .modal-actions-header { display: flex; gap: 0.75rem; margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb; }
            .action-button { padding: 0.5rem 1rem; border-radius: 0.375rem; font-weight: 500; color: white; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; border: none; cursor: pointer; }
                .pdf-button { background-color: #35dc35ff; border: #199e19ff solid 2px;}
                .pdf-button:hover { background-color: #229705ff; border: #1a7203ff solid 2px;}
            .print-button { background-color: #007bff; }
            .print-button:hover { background-color: #0069d9; }

            @media print {
                body * { visibility: hidden; }
                .no-print, .no-print * { display: none !important; }
                .printable-area, .printable-area * { visibility: visible; }
                .printable-area { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; }
                .modal-table { font-size: 10pt; }
                .modal-title { font-size: 14pt; text-align: center; }
            }
        </style>
        @endonce
    @endif
</div>