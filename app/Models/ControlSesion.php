<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ControlSesion extends Model
{
    use HasFactory;

    protected $table = 'control_sesiones';

    protected $fillable = [
        'dia',
        'hora_apertura',
        'hora_cierre',
        'cerrado_manual',
    ];

    protected $casts = [
        // Convierte a instancia de Carbon; al formatear solo muestra hora
        'hora_apertura' => 'datetime:H:i',
        'hora_cierre'   => 'datetime:H:i',
        'cerrado_manual'=> 'boolean',
    ];

    /**
     * Retorna el registro correspondiente al día de la semana actual.
     *
     * @return self|null
     */
    public static function hoy(): ?self
    {
        $nombres = [
            0 => 'Domingo',
            1 => 'Lunes',
            2 => 'Martes',
            3 => 'Miércoles',
            4 => 'Jueves',
            5 => 'Viernes',
            6 => 'Sábado',
        ];

        $clave   = now()->dayOfWeek;
        $nombre  = $nombres[$clave] ?? null;

        if (! $nombre) {
            return null;
        }

        return self::where('dia', $nombre)->first();
    }
}
