<?php

namespace App\Filament\Widgets;

use App\Models\Cliente;
use Filament\Widgets\BarChartWidget;

class ClientesPorReputacionChart extends BarChartWidget
{

    public static function canView(): bool
    {
        return auth()->user()?->can('stats.index') ?? false;
    }

    protected static ?string $heading = 'Clientes por Calificación';
    protected static ?int $sort = 2; // Puedes ajustar el orden del widget
    protected int|string|array $columnSpan = '1'; // O 'md' para responsividad

    protected function getData(): array
    {
        // Obtener el conteo de clientes por reputación (1 a 5)
        $clientes = Cliente::all();

        $conteo = collect(range(1, 5))->mapWithKeys(fn ($i) => [$i => 0]);

        foreach ($clientes as $cliente) {
            $reputacion = $cliente->reputacion;
            $conteo[$reputacion] = $conteo[$reputacion] + 1;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Cantidad de clientes',
                    'data' => $conteo->values()->toArray(),
                    'backgroundColor' => [
                        '#d32f2f', '#f57c00', '#fbc02d', '#388e3c', '#1976d2',
                    ],
                ],
            ],
            'labels' => ['Muy mala (1)', 'Mala (2)', 'Regular (3)', 'Buena (4)', 'Excelente (5)'],
        ];
    }
}
