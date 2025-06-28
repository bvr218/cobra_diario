<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DineroBase extends Model
{
    use SoftDeletes;

    protected $table = 'dinero_bases';

    protected $fillable = [
        'user_id',
        'monto',
        'monto_general',
        'monto_inicial',
        'dinero_en_mano',
    ];

    /**
     * Relación con el usuario.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
