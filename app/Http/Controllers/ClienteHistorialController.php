<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cliente;
use App\Exports\ClienteHistorialExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ClienteHistorialController extends Controller
{
    /**
     * Descarga el historial completo en Excel.
     */
    public function exportExcel(Cliente $cliente)
    {
        // 1) Cargamos al cliente con sus relaciones igual que en Livewire
        $clienteConDatos = Cliente::with([
            'prestamos' => function ($q) {
                $q->orderBy('created_at', 'desc')
                  ->with([
                      'abonos' => function ($q2) {
                          $q2->orderBy('fecha_abono', 'desc')
                             ->with('registradoPor');
                      },
                      'refinanciamientos' => function ($q3) {
                          $q3->orderBy('created_at', 'desc');
                      },
                      'registrado',
                  ]);
            }
        ])->findOrFail($cliente->id);

        // 2) Extraemos préstamos, abonos y refinanciaciones en Collections
        $prestamos = $clienteConDatos->prestamos;
        $abonos    = $prestamos
            ->flatMap(fn($p) => $p->abonos ?? collect())
            ->sortByDesc(fn($a) => Carbon::parse($a->fecha_abono));
        $refis     = $prestamos
            ->flatMap(fn($p) => $p->refinanciamientos ?? collect())
            ->sortByDesc(fn($r) => Carbon::parse($r->created_at));

        // 3) Generamos el Excel usando tu clase ClienteHistorialExport
        $slugCliente = Str::slug($clienteConDatos->nombre);
        $fileName    = 'historial_cliente_' . $slugCliente . '.xlsx';

        return Excel::download(
            new ClienteHistorialExport($clienteConDatos, $prestamos, $abonos, $refis),
            $fileName
        );
    }

    /**
     * Descarga el historial completo en PDF.
     */
    public function exportPdf(Cliente $cliente)
    {
        // 1) Cargamos los datos idénticos a exportExcel
        $clienteConDatos = Cliente::with([
            'prestamos' => function ($q) {
                $q->orderBy('created_at', 'desc')
                  ->with([
                      'abonos' => function ($q2) {
                          $q2->orderBy('fecha_abono', 'desc')
                             ->with('registradoPor');
                      },
                      'refinanciamientos' => function ($q3) {
                          $q3->orderBy('created_at', 'desc');
                      },
                      'registrado',
                  ]);
            }
        ])->findOrFail($cliente->id);

        $prestamos = $clienteConDatos->prestamos;
        $abonos    = $prestamos
            ->flatMap(fn($p) => $p->abonos ?? collect())
            ->sortByDesc(fn($a) => Carbon::parse($a->fecha_abono));
        $refis     = $prestamos
            ->flatMap(fn($p) => $p->refinanciamientos ?? collect())
            ->sortByDesc(fn($r) => Carbon::parse($r->created_at));

        // 2) Preparamos los datos para la vista PDF
        $data = [
            'cliente'          => $clienteConDatos,
            'prestamos'        => $prestamos,
            'abonos'           => $abonos,
            'refinanciaciones' => $refis,
            'carbon'           => Carbon::class,
        ];

        // 3) Cargamos la vista situada en resources/views/exports/cliente-historial-pdf.blade.php
        $pdf = Pdf::loadView('exports.cliente-historial-pdf', $data)
                  ->setPaper('A4', 'landscape');

        $slugCliente = Str::slug($clienteConDatos->nombre);
        $fileName    = 'historial_cliente_' . $slugCliente . '.pdf';

        // 4) Retornamos la respuesta de descarga
        return response()->streamDownload(fn() => print($pdf->output()), $fileName);
    }
}
