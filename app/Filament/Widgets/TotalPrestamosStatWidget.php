<?php

namespace App\Filament\Widgets;

use App\Models\Prestamo;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TotalPrestamosStatWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 1;
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('stats-agente.index') ?? false;
    }

    protected function getStats(): array
    {
        $usuarioId = Auth::id();

        $totalPrestamosActivos = Prestamo::where('agente_asignado', $usuarioId)
            ->whereIn('estado', ['activo', 'autorizado'])
            ->count();

        return [
            Stat::make('Préstamos Totales', $totalPrestamosActivos)
                ->description('Cantidad de Prestamos Autorizados')
                ->descriptionIcon('heroicon-o-briefcase')
                ->color('info'),
        ];
    }
}