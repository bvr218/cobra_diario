<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Services\LiquidationDataCollectionService;
use Illuminate\Support\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class LiquidacionPDFController extends Controller
{
    /**
     * Exporta una lista detallada de la liquidación a PDF.
     *
     * @param string $tipo El tipo de lista (ej: 'prestamos', 'recaudos').
     * @param int $agentId El ID del agente.
     * @param int $year El año de la liquidación.
     * @param int $month El mes de la liquidación.
     * @return \Illuminate\Http\Response
     */
    public function exportListToPdf(string $tipo, int $agentId, int $year, int $month)
    {
        $agent = User::find($agentId);
        if (!$agent) {
            abort(404, 'Agente no encontrado');
        }

        // Determinar el rango de fechas
        $isLive = ($year == now()->year && $month == now()->month);
        $fechaInicio = Carbon::create($year, $month, 1)->startOfMonth();
        $fechaFin = $isLive ? now() : $fechaInicio->copy()->endOfMonth();

        // Obtener los datos usando el servicio
        $liquidationService = app(LiquidationDataCollectionService::class);
        $listasDetalladas = $liquidationService->getDetailedLists($agent, $fechaInicio, $fechaFin);

        $data = $listasDetalladas[$tipo] ?? [];

        // Mapeo de tipos a títulos y vistas
        $map = [
            'prestamos' => 'Préstamos Entregados',
            'recaudos' => 'Recaudos Realizados',
            'refinanciaciones' => 'Refinanciaciones',
            'comisiones' => 'Comisiones y Seguros',
            'gastos' => 'Gastos',
            'prestamos_finalizados' => 'Préstamos Finalizados',
        ];

        if (!isset($map[$tipo])) {
            abort(404, 'Tipo de lista no válido');
        }

        $titulo = $map[$tipo];
        
        $pdfData = [
            'titulo' => $titulo,
            'lista' => $data,
            'agente' => $agent->name,
            'periodo' => $fechaInicio->format('d/m/Y') . ' - ' . $fechaFin->format('d/m/Y'),
            'valorCampo' => $tipo === 'prestamos' ? 'valor_total_prestamo' : null, // Ejemplo para préstamos
        ];

        // Cargar la vista específica del PDF
        $pdf = Pdf::loadView('exports.liquidacion-detalle-pdf', $pdfData)
                  ->setPaper('A4', 'landscape');

        $slugAgent = Str::slug($agent->name);
        $fileName = "liquidacion_{$tipo}_{$slugAgent}_{$year}-{$month}.pdf";

        return $pdf->download($fileName);
    }
}