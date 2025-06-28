<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Cliente;
use App\Models\Prestamo;
use Carbon\Carbon;

class StatsOverview extends BaseWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->can('stats.index') ?? false;
    }

    protected function getStats(): array
    {
        $primerPrestamoPendiente = Prestamo::where('estado', 'pendiente')->first();
        $primerPrestamoAtrasado = Prestamo::whereIn('estado', ['activo', 'autorizado'])->get()
        ->first(function ($prestamo) {
            return $prestamo->next_payment && $prestamo->next_payment->lt(now());
        });
        $primerPrestamoReducido = $this->getPrimerPrestamoConDeudaReducida();

        return [
            Stat::make('Clientes', Cliente::count())
                ->label('Clientes')
                ->icon('heroicon-o-users')
                ->color('success')
                ->description('Total de clientes registrados')
                ->url(route('filament.admin.resources.clientes.index')),

            Stat::make('Clientes con préstamo', Cliente::whereHas('prestamos')->count())
                ->label('Clientes con préstamo')
                ->icon('heroicon-o-currency-dollar')
                ->color('success')
                ->description('Clientes actualmente con préstamos')
                ->url(route('filament.admin.resources.clientes.index')),

            Stat::make('Préstamos activos o autorizados', Prestamo::whereIn('estado', ['activo', 'autorizado'])->count())
                ->label('Préstamos activos o autorizados')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->description('Total de préstamos activos o autorizados')
                ->url(route('filament.admin.resources.prestamos.index')),

            Stat::make('Préstamos pendientes', Prestamo::where('estado', 'pendiente')->count())
                ->label('Préstamos pendientes')
                ->icon('heroicon-o-exclamation-circle')
                ->color('warning')
                ->description('Préstamos en espera de autorización')
                ->url($primerPrestamoPendiente ? route('filament.admin.resources.prestamos.edit', $primerPrestamoPendiente->id) : null),

            Stat::make('Préstamos con pagos atrasados', $this->getPrestamosConPagoAtrasado())
                ->label('Préstamos atrasados')
                ->icon('heroicon-o-exclamation-circle')
                ->color('warning')
                ->description('Cantidad de préstamos vencidos o con pagos atrasados')
                ->url($primerPrestamoAtrasado ? route('filament.admin.resources.prestamos.edit', $primerPrestamoAtrasado->id) : null),

            Stat::make('Préstamos con menos del 30% de deuda', $this->getPrestamosConDeudaReducida())
                ->label('Préstamos sin refinanciar')
                ->icon('heroicon-o-arrow-left-on-rectangle')
                ->color('danger')
                ->description('Préstamos con menos del 30% de deuda restante')
                ->url($primerPrestamoReducido ? route('filament.admin.resources.prestamos.edit', $primerPrestamoReducido->id) : null),

            // NUEVO STAT: Préstamos con next_payment atrasado
        ];
    }

    protected function getPrestamosConDeudaReducida(): int
    {
        return Prestamo::get()->filter(function ($prestamo) {
            $deuda = $prestamo->deuda_actual;
            $valorTotal = $prestamo->valor_total_prestamo;

            if ($valorTotal <= 0) return false;

            $porcentaje = ($deuda / $valorTotal) * 100;

            return $deuda > 0 && $porcentaje < 30;
        })->count();
    }

    protected function getPrimerPrestamoConDeudaReducida(): ?Prestamo
    {
        return Prestamo::get()->first(function ($prestamo) {
            $deuda = $prestamo->deuda_actual;
            $valorTotal = $prestamo->valor_total_prestamo;

            if ($valorTotal <= 0) return false;

            $porcentaje = ($deuda / $valorTotal) * 100;

            return $deuda > 0 && $porcentaje < 30;
        });
    }

    /**
     * Cuenta los préstamos cuyo next_payment sea anterior a hoy (pagos atrasados).
     */
    protected function getPrestamosConPagoAtrasado(): int
    {
        $hoy = Carbon::today();

        return Prestamo::whereIn('estado', ['activo', 'autorizado'])
            ->get()
            ->filter(function ($prestamo) use ($hoy) {
                $nextPayment = $prestamo->next_payment; // Carbon o null

                return $nextPayment !== null && $nextPayment->lt($hoy);
            })->count();
    }

}
