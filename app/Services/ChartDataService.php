<?php

namespace App\Services;

use App\Models\Prestamo;
use App\Models\Refinanciamiento;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
// ¡Importante! Asegúrate de que CarbonPeriod esté importado o usa la ruta completa.
use Carbon\CarbonPeriod;

class ChartDataService
{
    /**
     * Prepara los datos para el gráfico de actividad diaria (Préstamos Nuevos vs. Refinanciaciones).
     *
     * @param User $agent
     * @param integer $year
     * @param integer $month
     * @return array
     */
    public function getDailyActivityChartData(User $agent, int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        if ($endDate->isFuture()) {
            $endDate = Carbon::now();
        }

        $newLoansQuery = Prestamo::where('agente_asignado', $agent->id)
            ->whereIn('estado', ['activo', 'autorizado'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereDoesntHave('refinanciamientos', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('created_at', [$startDate, $endDate]);
            });

        $totalNewLoans = (clone $newLoansQuery)->count();

        $newLoansDaily = $newLoansQuery->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get()
            ->pluck('count', 'date');

        $refinancingsQuery = Refinanciamiento::where('estado', 'autorizado')
            ->whereHas('prestamo', function ($query) use ($agent) {
                $query->where('agente_asignado', $agent->id);
            })
            ->whereBetween('created_at', [$startDate, $endDate]);

        $totalRefinancings = (clone $refinancingsQuery)->count();

        $refinancingsDaily = $refinancingsQuery->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->get()
            ->pluck('count', 'date');


        // --- INICIO DE LA CORRECCIÓN ---
        // Se reemplaza el bucle manual por CarbonPeriod, que es más seguro y preciso.
        
        $period = CarbonPeriod::create($startDate, $endDate);

        $categories = [];
        $loanData = [];
        $refinancingData = [];

        foreach ($period as $date) {
            $dateString = $date->toDateString();
            
            // Añade el día formateado ('01', '02', etc.) a las categorías
            $categories[] = $date->format('d');

            // Obtiene los datos para ese día o 0 si no existen
            $loanData[] = $newLoansDaily->get($dateString, 0);
            $refinancingData[] = $refinancingsDaily->get($dateString, 0);
        }
        // --- FIN DE LA CORRECCIÓN ---


        return [
            'series' => [
                [
                    'name' => 'Préstamos Nuevos',
                    'data' => $loanData,
                ],
                [
                    'name' => 'Refinanciaciones',
                    'data' => $refinancingData,
                ],
            ],
            'categories' => $categories,
            'totals' => [
                'loans' => $totalNewLoans,
                'refinancings' => $totalRefinancings,
            ],
        ];
    }

    /**
     * Prepara los datos para el gráfico de comparación entre agentes.
     *
     * @param integer $year
     * @param integer $month
     * @return array
     */
    public function getAgentComparisonChartData(int $year, int $month): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        if ($endDate->isFuture()) {
            $endDate = Carbon::now();
        }

        $agents = User::role('agente')->orderBy('name')->get();
        $agentNames = $agents->pluck('name')->toArray();
        
        $loanData = [];
        $refinancingData = [];

        foreach ($agents as $agent) {
            $loanCount = Prestamo::where('agente_asignado', $agent->id)
                ->whereIn('estado', ['activo', 'autorizado'])
                ->whereBetween('created_at', [$startDate, $endDate])
                ->whereDoesntHave('refinanciamientos', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->count();
            $loanData[] = $loanCount;

            $refinancingCount = Refinanciamiento::where('estado', 'autorizado')
                ->whereHas('prestamo', function ($query) use ($agent) {
                    $query->where('agente_asignado', $agent->id);
                })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
            $refinancingData[] = $refinancingCount;
        }

        return [
            'series' => [
                [
                    'name' => 'Préstamos Nuevos',
                    'data' => $loanData,
                ],
                [
                    'name' => 'Refinanciaciones',
                    'data' => $refinancingData,
                ],
            ],
            'categories' => $agentNames,
        ];
    }
}