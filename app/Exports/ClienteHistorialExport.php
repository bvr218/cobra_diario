<?php

namespace App\Exports;

use App\Models\Cliente;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ClienteHistorialExport implements WithMultipleSheets
{
    protected Cliente $cliente;
    protected Collection $prestamos;
    protected Collection $abonos;
    protected Collection $refinanciaciones;

    public function __construct(Cliente $cliente, Collection $prestamos, Collection $abonos, Collection $refinanciaciones)
    {
        $this->cliente = $cliente;
        $this->prestamos = $prestamos;
        $this->abonos = $abonos;
        $this->refinanciaciones = $refinanciaciones;
    }

    public function sheets(): array
    {
        $sheets = [];

        $sheets[] = new PrestamosSheet($this->prestamos);
        $sheets[] = new AbonosSheet($this->abonos);
        $sheets[] = new RefinanciacionesSheet($this->refinanciaciones);

        return $sheets;
    }
}

// Hoja para Préstamos
class PrestamosSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    private Collection $prestamos;

    public function __construct(Collection $prestamos)
    {
        $this->prestamos = $prestamos;
    }

    public function collection()
    {
        return $this->prestamos->map(function ($prestamo) {
            return [
                'ID' => $prestamo->id,
                'Valor' => $prestamo->valor_total_prestamo,
                'Estado' => ucfirst($prestamo->estado),
                'Registrado por' => $prestamo->registrado?->name,
                'Fecha' => Carbon::parse($prestamo->created_at)->format('d-m-Y'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID',
            'Valor Préstamo',
            'Estado',
            'Registrado por',
            'Fecha Registro',
        ];
    }

    public function title(): string
    {
        return 'Préstamos';
    }

    public function styles(Worksheet $sheet)
    {
        // Aplicar negrita a la primera fila (encabezados)
        $sheet->getStyle('1:1')->getFont()->setBold(true);

        // Centrar la columna de Valor (B)
        $sheet->getStyle('B')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Aplicar bordes a todas las celdas con datos
        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = $sheet->getHighestDataColumn();
        $cellRange = 'A1:' . $lastColumn . $lastRow;

        return [
            $cellRange => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ],
        ];
    }
}

// Hoja para Abonos
class AbonosSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    private Collection $abonos;

    public function __construct(Collection $abonos)
    {
        $this->abonos = $abonos;
    }

    public function collection()
    {
        return $this->abonos->map(function ($abono) {
            return [
                'Préstamo ID' => $abono->prestamo_id,
                'Monto Abonado' => $abono->monto_abono,
                'Cuota #' => $abono->numero_cuota,
                'Registrado por' => $abono->registradoPor?->name,
                'Fecha Abono' => Carbon::parse($abono->fecha_abono)->format('d-m-Y'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Préstamo ID',
            'Monto Abonado',
            'Cuota #',
            'Registrado por',
            'Fecha Abono',
        ];
    }

    public function title(): string
    {
        return 'Abonos';
    }

    public function styles(Worksheet $sheet)
    {
        // Aplicar negrita a la primera fila (encabezados)
        $sheet->getStyle('1:1')->getFont()->setBold(true);

        // Centrar la columna de Monto Abonado (B) y Cuota # (C)
        $sheet->getStyle('B')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Aplicar bordes a todas las celdas con datos
        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = $sheet->getHighestDataColumn();
        $cellRange = 'A1:' . $lastColumn . $lastRow;

        return [
            $cellRange => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ],
        ];
    }
}

// Hoja para Refinanciaciones
class RefinanciacionesSheet implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize
{
    private Collection $refinanciaciones;

    public function __construct(Collection $refinanciaciones)
    {
        $this->refinanciaciones = $refinanciaciones;
    }

    public function collection()
    {
        return $this->refinanciaciones->map(function ($r) {
            return [
                'ID' => $r->id,
                'Valor Ingresado' => $r->valor,
                'Interés (%)' => $r->interes,
                'Total con Interés' => $r->total,
                'Fecha' => Carbon::parse($r->fecha_refinanciacion ?? $r->created_at)->format('d-m-Y'),
                'ID Préstamo Original' => $r->prestamo_id,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ID Refinanciación',
            'Valor Ingresado',
            'Interés (%)',
            'Total con Interés',
            'Fecha',
            'ID Préstamo Original',
        ];
    }

    public function title(): string
    {
        return 'Refinanciaciones';
    }

    public function styles(Worksheet $sheet)
    {
        // Aplicar negrita a la primera fila (encabezados)
        $sheet->getStyle('1:1')->getFont()->setBold(true);

        // Centrar las columnas de Valor Ingresado (B), Interés (C), Total con Interés (D)
        $sheet->getStyle('B')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('C')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('D')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Aplicar bordes a todas las celdas con datos
        $lastRow = $sheet->getHighestDataRow();
        $lastColumn = $sheet->getHighestDataColumn();
        $cellRange = 'A1:' . $lastColumn . $lastRow;

        return [
            $cellRange => [
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ],
        ];
    }
}
