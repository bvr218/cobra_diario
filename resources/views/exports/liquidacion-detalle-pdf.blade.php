<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }} - {{ $agente }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        h1, h2 { text-align: center; }
        h1 { font-size: 16px; margin-bottom: 5px; }
        h2 { font-size: 12px; font-weight: normal; margin-top: 0; color: #555; }
        .text-right { text-align: right !important; }
        .text-center { text-align: center !important; }
        .capitalize { text-transform: capitalize; }
    </style>
</head>
<body>
    <h1>{{ $titulo }}</h1>
    <h2>Agente: {{ $agente }} | Período: {{ $periodo }}</h2>

    @if(empty($lista))
        <p class="text-center">No hay datos disponibles para mostrar en esta lista.</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach(array_keys((array)$lista[0]) as $header)
                        <th>{{ ucfirst(str_replace('_', ' ', $header)) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($lista as $item)
                    <tr>
                        @foreach($item as $key => $value)
                            <td>
                                @if(is_numeric($value) && (str_contains($key, 'valor') || str_contains($key, 'monto') || str_contains($key, 'deuda')))
                                    ${{ number_format($value, 0, ',', '.') }}
                                @elseif(is_bool($value))
                                    {{ $value ? 'Sí' : 'No' }}
                                @else
                                    {{ $value }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>