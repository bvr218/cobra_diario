{{-- resources/views/livewire/cliente-historial-modal.blade.php --}}
@php
    use Carbon\Carbon;
    use App\Filament\Resources\PrestamoResource;
    use App\Filament\Resources\AbonoResource;

    $canPrestamos = auth()->user()->can('prestamos.index');
    $canAbonos    = auth()->user()->can('abonos.index');
@endphp


<div class="space-y-6 w-full printable-modal" x-data="printModal()"> {{-- Mantener x-data="printModal()" --}}

    {{-- ─────────────── Botones de Acción ─────────────── --}}
    {{-- A este div ya le has puesto la clase 'no-print', lo cual es bueno. --}}
    <div class="no-print flex flex-col sm:flex-row justify-end items-center space-y-2 sm:space-y-0 sm:space-x-2 mb-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-md shadow">
        {{-- Enlace para Exportar a Excel --}}
        <a
            href="{{ route('clientes.historial.excel', ['cliente' => $cliente->id]) }}"
            target="_blank"
            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium bg-green-600 border border-transparent rounded-md shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:text-white dark:bg-green-500 dark:hover:bg-green-600 w-full sm:w-auto"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v2a2 2 0 100 4v2a2 2 0 01-2 2H4a2 2 0 01-2-2V6zm5 2V6h2v2H7zm4 0V6h2v2h-2zm4 0V6h2v2h-2zm-8 4v-2H7v2h2zm4 0v-2h-2v2h2zm4 0v-2h-2v2h2z" clip-rule="evenodd" fill-rule="evenodd"></path>
            </svg>
            Exportar a Excel
        </a>

        {{-- Enlace para Exportar a PDF --}}
        <a
            href="{{ route('clientes.historial.pdf', ['cliente' => $cliente->id]) }}"
            target="_blank"
            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium bg-red-600 border border-transparent rounded-md shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:text-white dark:bg-red-500 dark:hover:bg-red-600 w-full sm:w-auto"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V4a2 2 0 00-2-2H5zm0 2h10v7.5a.5.5 0 01-.5.5H5.5a.5.5 0 01-.5-.5V4zM5.5 13H12v1.5a.5.5 0 01-.5.5h-5a.5.5 0 01-.5-.5V13z" clip-rule="evenodd" />
                <path d="M13 8.5a.5.5 0 00-.5-.5H7.5a.5.5 0 000 1H12a.5.5 0 00.5-.5z" />
            </svg>
            Exportar a PDF
        </a>

        {{-- Botón de Imprimir (AHORA USA x-on:click="print()") --}}
        <button
            x-on:click="print()" {{-- ¡ESTO ES CRUCIAL! --}}
            type="button"
            class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-gray-700 bg-blue-100 border border-transparent rounded-md shadow-sm hover:bg-blue-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:text-gray-200 dark:bg-blue-600 dark:hover:bg-blue-700 w-full sm:w-auto"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v2H2a2 2 0 00-2 2v8a2 2 0 002 2h1v-2H2a.5.5 0 01-.5-.5V8a.5.5 0 01.5-.5h14a.5.5 0 01.5.5v5.5a.5.5 0 01-.5.5H17v2h1a2 2 0 002-2V8a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H5zm10 2H5v2h10V4zM4 9a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zm1 3a1 1 0 100 2h8a1 1 0 100-2H5z" clip-rule="evenodd" />
            </svg>
            Imprimir
        </button>

    </div>
    {{-- ─────────────────────────────────────────────────────── --}}

    {{-- 1) Préstamos --}}
    <div>
        <h3 class="text-lg sm:text-xl font-semibold text-gray-700 dark:text-gray-200">Préstamos</h3>
        @if($prestamos->isEmpty())
            <p class="mt-2 text-gray-600 dark:text-gray-400">No hay préstamos registrados.</p>
        @else
            <div class="mt-4 overflow-x-auto w-full">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Valor</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Estado</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Registrado por</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($prestamos as $prestamo)
                            @php
                                $url = PrestamoResource::getUrl('edit', ['record' => $prestamo]);
                            @endphp
                            <tr
                                @if($canPrestamos)
                                    class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                                    onclick="window.location='{{ $url }}'"
                                @endif
                            >
                                <td class="px-4 py-3 whitespace-nowrap {{ $canPrestamos ? 'text-blue-600 hover:underline' : '' }}">
                                    {{ $prestamo->id }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    ${{ number_format($prestamo->valor_total_prestamo, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="inline-block px-2 py-1 text-xs font-semibold rounded-full
                                        {{ $prestamo->estado === 'activo' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                        {{ ucfirst($prestamo->estado) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $prestamo->registrado?->name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ Carbon::parse($prestamo->created_at)->format('d-m-Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 2) Abonos --}}
    <div>
        <h3 class="text-lg sm:text-xl font-semibold text-gray-700 dark:text-gray-200">Abonos</h3>
        @if($abonos->isEmpty())
            <p class="mt-2 text-gray-600 dark:text-gray-400">No hay abonos registrados.</p>
        @else
            <div class="mt-4 overflow-x-auto w-full">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Préstamo ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Monto Abonado</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Cuota #</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Registrado por</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Fecha</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($abonos as $abono)
                            @php
                                $urlA = AbonoResource::getUrl('edit', ['record' => $abono]);
                            @endphp
                            <tr
                                @if($canAbonos)
                                    class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                                    onclick="window.location='{{ $urlA }}'"
                                @endif
                            >
                                <td class="px-4 py-3 whitespace-nowrap {{ $canAbonos ? 'text-blue-600 hover:underline' : '' }}">
                                    {{ $abono->prestamo_id }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    ${{ number_format($abono->monto_abono, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $abono->numero_cuota }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $abono->registradoPor?->name }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ Carbon::parse($abono->fecha_abono)->format('d-m-Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- 3) Refinanciaciones --}}
    <div>
        <h3 class="text-lg sm:text-xl font-semibold text-gray-700 dark:text-gray-200">Refinanciaciones</h3>
        @if($refinanciaciones->isEmpty())
            <p class="mt-2 text-gray-600 dark:text-gray-400">No hay refinanciaciones registradas.</p>
        @else
            <div class="mt-4 overflow-x-auto w-full">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">ID</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Valor Ingresado</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Interés</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Total con Interés</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">Fecha</th>
                            <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-300">ID del Prestamo</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($refinanciaciones as $r)
                            @php
                                $urlP = PrestamoResource::getUrl('edit', ['record' => $r->prestamo]);
                            @endphp
                            <tr
                                @if($canPrestamos)
                                    class="cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-700"
                                    onclick="window.location='{{ $urlP }}'"
                                @endif
                            >
                                <td class="px-4 py-3 whitespace-nowrap {{ $canPrestamos ? 'text-blue-600 hover:underline' : '' }}">
                                    {{ $r->id }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    ${{ number_format($r->valor, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $r->interes }}%
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    ${{ number_format($r->total, 0, ',', '.') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ Carbon::parse($r->fecha_refinanciacion ?? $r->created_at)->format('d-m-Y') }}
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $r->prestamo_id }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>