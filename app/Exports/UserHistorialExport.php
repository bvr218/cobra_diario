<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use App\Models\User;
use App\Models\Prestamo;
use Carbon\Carbon;

class UserHistorialExport implements WithMultipleSheets
{
    protected $user;

    public function __construct(User $user)
    {
        $this->user = $user;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Hoja de Préstamos Registrados
        $sheets[] = new class($this->user) implements FromCollection, WithHeadings, WithTitle, WithStyles, ShouldAutoSize {
            private $user;

            public function __construct(User $user)
            {
                $this->user = $user;
            }

            public function collection()
            {
                // Carga los préstamos registrados por el usuario, ordenados por fecha de creación
                // Simplificado para cargar solo la relación 'cliente'
                $prestamos = $this->user->prestamos()
                                        ->with('cliente') 
                                        ->orderBy('posicion_ruta', 'asc') // Ordenar por posicion_ruta ascendente
                                        ->get();

                return $prestamos->map(function (Prestamo $prestamo) {
                    return [
                        'Nombre' => $prestamo->cliente->nombre ?? 'N/A', // Cambiado de 'Cliente' a 'Nombre'
                        'Deuda Inicial' => $prestamo->deuda_inicial,
                        'Deuda Actual' => $prestamo->deuda_actual,
                        'Estado' => ucfirst($prestamo->estado),
                    ];
                });
            }

            public function headings(): array
            {
                return [
                    'Nombre', // Cambiado de 'Cliente' a 'Nombre'
                    'Deuda Inicial',
                    'Deuda Actual',
                    'Estado',
                ];
            }

            public function title(): string
            {
                return 'Ruta ' . $this->user->name; // Título de la hoja con el nombre del usuario
            }

            public function styles(Worksheet $sheet)
            {
                // Aplicar negrita a la primera fila (encabezados)
                $sheet->getStyle('1:1')->getFont()->setBold(true);

                // Aplicar negrita a la columna A (Nombre del cliente)
                $sheet->getStyle('A')->getFont()->setBold(true);

                // Centrar las columnas de Deuda Inicial (B) y Deuda Actual (C)
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
        };

        return $sheets;
    }
}