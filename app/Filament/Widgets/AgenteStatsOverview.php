<?php

namespace App\Filament\Widgets;

use App\Models\Abono;
use App\Models\Prestamo;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AgenteStatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    // La cuadrícula base sigue siendo de 2 columnas.
    protected function getColumns(): int
    {
        return 2;
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('stats-agente.index') ?? false;
    }

    protected function getStats(): array
    {
        // --- 1. Obtener información de la fecha y del usuario actual ---
        $hoy = Carbon::today();
        $usuarioId = Auth::id();
        $nombreDia = ucfirst($hoy->locale('es')->dayName);
        $descripcionDia = "($nombreDia)";

        // --- 2. Cálculos para los cobros REALIZADOS HOY ---
        $abonosDelDia = Abono::where('registrado_por_id', $usuarioId)
            ->whereDate('created_at', $hoy)
            ->get();

        $totalRecaudosHoy = $abonosDelDia->count();
        $totalDineroHoy = $abonosDelDia->sum('monto_abono');


        // --- 3. Cálculos para los cobros PENDIENTES/VENCIDOS ---
        $recaudosPendientesCount = 0;
        $totalDineroPendiente = 0;

        $prestamosActivosAgente = Prestamo::where('agente_asignado', $usuarioId)
            ->whereIn('estado', ['activo', 'autorizado'])
            ->get();

        foreach ($prestamosActivosAgente as $prestamo) {
            if ($prestamo->next_payment && Carbon::parse($prestamo->next_payment)->lte($hoy)) {
                $recaudosPendientesCount++;
                $totalDineroPendiente += $prestamo->monto_por_cuota;
            }
        }

        // --- 4. Construir y devolver las 4 estadísticas ---
        return [
            // **CAMBIO CLAVE: Hacer que esta stat TAMBIÉN ocupe una fila completa.**
            Stat::make('Total Dinero Faltante', '$' . number_format($totalDineroPendiente, 0, ',', '.'))
                ->description('Dinero Por Cobrar')
                ->descriptionIcon('heroicon-o-currency-dollar')
                ->color('danger')
                ->extraAttributes([
                    'class' => 'md:col-span-2', // Ocupa 2 columnas en la cuadrícula
                ]),
            // **CAMBIO CLAVE: Hacer que esta stat ocupe una fila completa.**
            Stat::make('Recaudos Faltantes', $recaudosPendientesCount)
                ->description('Prestamos Vencidos')
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('warning')
                ->extraAttributes([
                    'class' => 'md:col-span-2', // Ocupa 2 columnas en la cuadrícula
                ]),

            // Estas dos stats se mantienen como estaban y compartirán la siguiente fila.
            Stat::make('Clientes Cobrados', $totalRecaudosHoy)
                ->description($descripcionDia)
                ->descriptionIcon('heroicon-o-receipt-refund')
                ->color('success'),
                
            Stat::make('Total Dinero Cobrado del Día', '$' . number_format($totalDineroHoy, 0, ',', '.'))
                ->description($descripcionDia)
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
        ];
    }
}