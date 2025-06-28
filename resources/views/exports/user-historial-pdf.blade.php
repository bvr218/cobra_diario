<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Usuario - {{ $user->name }}</title>
    <style>
        /* Agrega o ajusta tus estilos CSS aquí para el PDF */
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            font-size: 10px; /* Reducir tamaño de fuente para mejor ajuste */
        }
        h1, h2 {
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 6px; /* Reducir padding */
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .text-right {
            text-align: right;
        }
        .header-info table {
            width: 100%;
            margin-bottom: 20px;
            border: none;
        }
        .header-info td {
            border: none;
            padding: 2px 0;
        }
    </style>
</head>
<body>
    <h1>Historial de Préstamos de {{ $user->name }}</h1>

    <h2>Préstamos Registrados</h2>
    @if ($prestamos->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Deuda Inicial</th>
                    <th>Deuda Actual</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($prestamos as $prestamo)
                    <tr>
                        <td>{{ $prestamo->cliente->nombre ?? 'Cliente Desconocido' }}</td>
                        <td class="text-right">${{ number_format($prestamo->deuda_inicial, 0, ',', '.') }}</td>
                        <td class="text-right">${{ number_format($prestamo->deuda_actual, 0, ',', '.') }}</td>
                        <td>{{ ucfirst($prestamo->estado) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="text-align: center;">Este usuario no tiene préstamos registrados.</p>
    @endif

    {{-- Secciones de Abonos y Refinanciaciones eliminadas según el requerimiento --}}

</body>
</html>