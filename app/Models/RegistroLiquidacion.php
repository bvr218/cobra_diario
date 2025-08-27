<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegistroLiquidacion extends Model
{
    use HasFactory;

    protected $table = 'registro_liquidaciones';

    protected $fillable = [
        'nombre',
        'user_id',
        'desde',
        'hasta',
        'datos_liquidacion',
        'type',
    ];

    protected $casts = [
        'desde' => 'datetime',
        'hasta' => 'datetime',
        'datos_liquidacion' => 'array',
        'type' => 'string',
    ];

    /**
     * Get the user that owns the LiquidacionGuardada.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}