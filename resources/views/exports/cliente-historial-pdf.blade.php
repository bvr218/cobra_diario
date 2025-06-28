{{-- resources/views/exports/cliente-historial-pdf.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Cliente - {{ $cliente->nombre }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; margin: 20px; font-size: 10px; }
        h1, h2, h3 { color: #333; }
        h1 { text-align: center; margin-bottom: 20px; font-size: 18px; }
        h2 { margin-top: 30px; margin-bottom: 10px; font-size: 16px; border-bottom: 1px solid #eee; padding-bottom: 5px;}
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f9f9f9; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .no-records { color: #777; text-align: center; padding: 10px; }
        .page-break { page-break-after: always; }
        .header-info { margin-bottom: 20px; font-size: 12px; }
        .header-info strong { display: inline-block; width: 120px; }
    </style>
</head>
<body>
    <h1>Historial de Cliente</h1>
    <div class="header-info">
        <p><strong>Cliente:</strong> {{ $cliente->nombre }}</p>
        <p><strong>Cédula:</strong> {{ $cliente->numero_cedula }}</p>
        <p><strong>Fecha de Reporte:</strong> {{ $carbon::now()->format('d-m-Y H:i') }}</p>
    </div>

    <h2>Préstamos</h2>
    @if($prestamos->isEmpty())
        <p class="no-records">No hay préstamos registrados.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Valor</th>
                    <th>Estado</th>
                    <th>Registrado por</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                @foreach($prestamos as $prestamo)
                    <tr>
                        <td>{{ $prestamo->id }}</td>
                        <td class="text-right">${{ number_format($prestamo->valor_total_prestamo, 0, ',', '.') }}</td>
                        <td>{{ ucfirst($prestamo->estado) }}</td>
                        <td>{{ $prestamo->registrado?->name ?? 'N/A' }}</td>
                        <td>{{ $carbon::parse($prestamo->created_at)->format('d-m-Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="page-break"></div>

    <h2>Abonos</h2>
    @if($abonos->isEmpty())
        <p class="no-records">No hay abonos registrados.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Préstamo ID</th>
                    <th>Monto Abonado</th>
                    <th>Cuota #</th>
                    <th>Registrado por</th>
                    <th>Fecha</th>
                </tr>
            </thead>
            <tbody>
                @foreach($abonos as $abono)
                    <tr>
                        <td>{{ $abono->prestamo_id }}</td>
                        <td class="text-right">${{ number_format($abono->monto_abono, 0, ',', '.') }}</td>
                        <td>{{ $abono->numero_cuota }}</td>
                        <td>{{ $abono->registradoPor?->name ?? 'N/A' }}</td>
                        <td>{{ $carbon::parse($abono->fecha_abono)->format('d-m-Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="page-break"></div>

    <h2>Refinanciaciones</h2>
    @if($refinanciaciones->isEmpty())
        <p class="no-records">No hay refinanciaciones registradas.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Valor Ingresado</th>
                    <th>Interés</th>
                    <th>Total con Interés</th>
                    <th>Fecha</th>
                    <th>ID Préstamo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($refinanciaciones as $r)
                    <tr>
                        <td>{{ $r->id }}</td>
                        <td class="text-right">${{ number_format($r->valor, 0, ',', '.') }}</td>
                        <td class="text-center">{{ $r->interes }}%</td>
                        <td class="text-right">${{ number_format($r->total, 0, ',', '.') }}</td>
                        <td>{{ $carbon::parse($r->fecha_refinanciacion ?? $r->created_at)->format('d-m-Y') }}</td>
                        <td>{{ $r->prestamo_id }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

</body>
</html>
