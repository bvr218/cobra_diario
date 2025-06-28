<?php

namespace App\Helpers;

use App\Models\HistorialMovimiento;
use Illuminate\Support\Facades\Auth;

class HistorialHelper
{
    public static function registrar(array $datos): void
    {
        HistorialMovimiento::create([
            'user_id' => $datos['user_id'] ?? Auth::id(),
            'tipo' => $datos['tipo'],
            'descripcion' => $datos['descripcion'] ?? null,
            'monto' => $datos['monto'] ?? 0,
            'fecha' => now(),
            'es_edicion' => $datos['es_edicion'] ?? false,
            'cambio_desde' => $datos['cambio_desde'] ?? null,
            'cambio_hacia' => $datos['cambio_hacia'] ?? null,
            'referencia_id' => $datos['referencia_id'] ?? null,
            'tabla_origen' => $datos['tabla_origen'] ?? null,
        ]);
    }
}
