<?php

namespace App\Filament\Resources\DineroBaseResource\Widgets;

use App\Models\DineroBase;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class DineroBaseTotal extends BaseWidget
{
    // Permite refrescar cuando cambian los filtros
    protected static bool $isLazy = false;
    protected static bool $isRefreshable = true;

    /**
     * Definimos aquí el filtro “Filtrar por Oficina”
     */
    protected function getFormSchema(): array
    {
        return [
            Select::make('office_user_id')
                ->label('Filtrar por Oficina')
                ->options(
                    // Solo usuarios con rol 'oficina'
                    User::role('oficina')
                        ->pluck('name', 'id')
                        ->toArray()
                )
                ->searchable()
                ->placeholder('Todas las oficinas'),
        ];
    }

    /**
     * Calculamos la estadística según el filtro aplicado
     */
    protected function getStats(): array
    {
        // ID del usuario-oficina seleccionado
        $officeUserId = $this->filterFormData['office_user_id'] ?? null;

        // Construimos la consulta
        $query = DineroBase::query()
            ->when($officeUserId, function ($q) use ($officeUserId) {
                // Sumamos solo quienes tienen oficina_id = seleccionado
                $q->whereHas('user', function ($u) use ($officeUserId) {
                    $u->where('oficina_id', $officeUserId);
                });
            });

        // Clonamos las consultas para cada cálculo
        $queryMonto = clone $query;
        $queryMontoCaja = clone $query;
        $queryMontoGeneral = clone $query;
        $queryMontoInicial = clone $query; // Nueva consulta para monto_inicial

        $totalMonto = $queryMonto->sum('monto');
        $formattedMonto = '$' . number_format($totalMonto, 0, ',', '.');

        $totalMontoCaja = $queryMontoCaja->sum('dinero_en_mano');
        $formattedMontoCaja = '$' . number_format($totalMontoCaja, 0, ',', '.');

        $totalMontoGeneral = $queryMontoGeneral->sum('monto_general');
        $formattedMontoGeneral = '$' . number_format($totalMontoGeneral, 0, ',', '.');

        $totalMontoInicial = $queryMontoInicial->sum('monto_inicial'); // Suma de monto_inicial
        $formattedMontoInicial = '$' . number_format($totalMontoInicial, 0, ',', '.'); // Formato para monto_inicial
        
        return [
            Stat::make('Monto Inicial', $formattedMontoInicial) // Etiqueta y valor para monto_inicial
                ->description(
                    $officeUserId
                        ? 'Monto inicial de la oficina seleccionada'
                        : 'Monto inicial'
                )
                ->descriptionIcon(
                    $totalMontoInicial >= 0
                        ? 'heroicon-o-arrow-trending-up'
                        : 'heroicon-o-arrow-trending-down'
                )
                ->color($totalMontoInicial >= 0 ? 'success' : 'info'), // Puedes cambiar el color según tu preferencia
            Stat::make('Total Dinero Base (Capital)', $formattedMontoGeneral)
                ->description(
                    $officeUserId
                        ? 'Total capital de la oficina seleccionada'
                        : 'Total capital general'
                        )
                ->descriptionIcon(
                    $totalMontoGeneral >= 0
                        ? 'heroicon-o-arrow-trending-up'
                        : 'heroicon-o-arrow-trending-down'
                )
                ->color($totalMontoGeneral >= 0 ? 'success' : 'primary'),
            Stat::make('Total Dinero en Caja', $formattedMontoCaja)
                ->description(
                    $officeUserId
                        ? 'Total en Caja de la oficina seleccionada'
                        : 'Total Caja en general'
                )
                ->descriptionIcon(
                    $totalMontoCaja >= 0
                        ? 'heroicon-o-arrow-trending-up'
                        : 'heroicon-o-arrow-trending-down'
                )
                ->color($totalMontoCaja >= 0 ? 'success' : 'danger'),
            Stat::make('Total Dinero en Mano', $formattedMonto)
                ->description(
                    $officeUserId
                        ? 'Total en Mano de la oficina seleccionada'
                        : 'Total en Mano en general'
                )
                ->descriptionIcon(
                    $totalMonto >= 0
                        ? 'heroicon-o-arrow-trending-up'
                        : 'heroicon-o-arrow-trending-down'
                )
                ->color($totalMonto >= 0 ? 'success' : 'danger'),

        ];
    }
}