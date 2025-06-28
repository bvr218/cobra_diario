<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class HistorialMovimiento extends Model
{
    use HasFactory;

    protected $table = 'historial_movimientos';

    protected $fillable = [
        'user_id',
        'tipo',
        'descripcion',
        'monto',
        'fecha',
        'es_edicion',
        'cambio_desde',
        'cambio_hacia',
        'referencia_id',
        'tabla_origen',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'es_edicion' => 'boolean',
        'cambio_desde' => 'array',
        'cambio_hacia' => 'array',
    ];

    // Relación con el modelo User
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
