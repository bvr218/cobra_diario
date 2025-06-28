<div  class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-lg">
    <div class="p-4 sm:p-6 lg:p-8 user-historial-printable-content printable-modal" x-data="printModal()">
    
        <div class="no-print flex flex-col sm:flex-row justify-end items-center space-y-2 sm:space-y-0 sm:space-x-2 mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-md shadow">
            <a
                href="{{ route('users.historial.excel', ['user' => $user->id]) }}"
                target="_blank"
                class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:text-white dark:bg-green-500 dark:hover:bg-green-600 w-full sm:w-auto"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 100 4v2a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm5 2V6h2v2H7zm4 0V6h2v2h-2zm4 0V6h2v2h-2zm-8 4v-2H7v2h2zm4 0v-2h-2v2h2zm4 0v-2h-2v2h2z" clip-rule="evenodd" fill-rule="evenodd"></path>
                </svg>
                Exportar a Excel
            </a>
    
            <a
                href="{{ route('users.historial.pdf', ['user' => $user->id]) }}"
                target="_blank"
                class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:text-white dark:bg-red-500 dark:hover:bg-red-600 w-full sm:w-auto"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V4a2 2 0 00-2-2H5zm0 2h10v7.5a.5.5 0 01-.5.5H5.5a.5.5 0 01-.5-.5V4zM5.5 13H12v1.5a.5.5 0 01-.5.5h-5a.5.5 0 01-.5-.5V13z" clip-rule="evenodd" />
                    <path d="M13 8.5a.5.5 0 00-.5-.5H7.5a.5.5 0 000 1H12a.5.5 0 00.5-.5z" />
                </svg>
                Exportar a PDF
            </a>
    
            <button
                type="button"
                x-on:click="print()"
                class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-blue-100 border border-transparent rounded-md shadow-sm hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:text-gray-200 dark:bg-blue-600 dark:hover:bg-blue-700 w-full sm:w-auto"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v2H2a2 2 0 00-2 2v8a2 2 0 002 2h1v-2H2a.5.5 0 01-.5-.5V8a.5.5 0 01.5-.5h14a.5.5 0 01.5.5v5.5a.5.5 0 01-.5.5H17v2h1a2 2 0 002-2V8a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H5zm10 2H5v2h10V4zM4 9a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm1 3a1 1 0 100 2h8a1 1 0 100-2H5z" clip-rule="evenodd" />
                </svg>
                Imprimir
            </button>
        </div>
    
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Préstamos Registrados</h3>
        @if($prestamosRegistrados->isEmpty())
            <div class="flex items-center justify-center p-4 text-sm text-gray-600 dark:text-gray-400">
                No hay préstamos registrados por este usuario.
            </div>
        @else
            <div class="overflow-x-auto rounded-lg shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                        <tr>
                            <th scope="col" class="px-6 py-3">Cliente</th>
                            <th scope="col" class="px-6 py-3">Deuda Inicial</th>
                            <th scope="col" class="px-6 py-3">Deuda Actual</th>
                            <th scope="col" class="px-6 py-3">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($prestamosRegistrados as $prestamo)
                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                    @if(Auth::user()->can('clientes.index'))
                                        <a href="{{ \App\Filament\Resources\ClienteResource::getUrl('edit', ['record' => $prestamo->cliente_id]) }}"
                                            class="text-primary-600 hover:underline"
                                        >
                                            {{ $prestamo->cliente->nombre ?? 'Cliente Desconocido' }}
                                        </a>
                                    @else
                                        {{ $prestamo->cliente->nombre ?? 'Cliente Desconocido' }}
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    ${{ number_format($prestamo->deuda_inicial, 0, ',', '.') }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="{{ $prestamo->deuda_actual > 0 ? 'text-red-600 dark:text-red-400 font-semibold' : 'text-green-600 dark:text-green-400 font-semibold' }}">
                                        ${{ number_format($prestamo->deuda_actual, 0, ',', '.') }}
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold
                                        @if($prestamo->estado === 'activo') bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200
                                        @elseif($prestamo->estado === 'pendiente') bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200
                                        @elseif($prestamo->estado === 'negado') bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200
                                        @elseif($prestamo->estado === 'finalizado') bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200
                                        @elseif($prestamo->estado === 'autorizado') bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200
                                        @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200 @endif">
                                        {{ ucfirst($prestamo->estado) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        <style>
            @media print {
                .no-print {
                    display: none !important;
                }
    
                body, html {
                    overflow: visible !important;
                    height: auto !important;
                    background-color: white !important;
                    color: black !important;
                }
                /* Asegurar que el contenido principal del modal sea visible y ocupe espacio */
                .printable-modal {
                    display: block !important;
                    width: 100% !important; /* Ocupa todo el ancho disponible */
                    height: auto !important;
                    overflow: visible !important;
                    padding: 10px !important; /* Un poco de padding para que no esté pegado a los bordes */
                    margin: 0 !important;
                    background-color: white !important;
                    color: black !important;
                }
    
                /* Resetear estilos de modo oscuro para impresión */
                .dark .dark\:bg-gray-800, .dark .dark\:bg-gray-700, .dark .dark\:bg-gray-900,
                .dark .dark\:text-white, .dark .dark\:text-gray-200, .dark .dark\:text-gray-400,
                .dark .dark\:text-green-200, .dark .dark\:text-blue-200, .dark .dark\:text-red-200,
                .dark .dark\:text-purple-200, .dark .dark\:text-yellow-200 {
                    background-color: white !important;
                    color: black !important;
                }
                .dark\:border-gray-700 { border-color: #e5e7eb !important; }
                .dark\:ring-white\/10 { ring-color: transparent !important; }
                .dark\:hover\:bg-gray-600:hover { background-color: #f9fafb !important; }
    
                /* Estilos para la tabla y su contenedor */
                .overflow-x-auto {
                    overflow-x: visible !important;
                    overflow-y: visible !important;
                    border: none !important;
                    box-shadow: none !important;
                    ring-width: 0 !important;
                }
                table {
                    width: 100% !important;
                    page-break-inside: auto !important;
                    border-collapse: collapse !important; /* Para que los bordes se vean bien */
                }
                thead { display: table-header-group !important; }
                tbody { display: table-row-group !important; }
                tr { page-break-inside: avoid !important; page-break-after: auto !important; }
                td, th {
                    page-break-inside: avoid !important;
                    border: 1px solid #ccc !important; /* Añadir bordes a las celdas para impresión */
                    padding: 6px !important; /* Ajustar padding para impresión */
                }
                th { background-color: #f2f2f2 !important; } /* Fondo para encabezados de tabla */
            }
        </style>
        <script>
            function printModal() {
                return {
                    print() {
                        window.print();
                    }
                }
            }
        </script>
    </div>
</div>