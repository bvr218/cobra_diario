<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;


class Gasto extends Model
{

    use SoftDeletes;    
    protected $fillable = [
        'user_id',
        'valor',
        'autorizado',
        'informacion',
        'imagen',
        'tipo_gasto_id'
    ];

    protected $casts = [
        'imagen' => 'array',
    ];

    public function user(): belongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tipoGasto(): BelongsTo
    {
        return $this->belongsTo(TipoGasto::class);
    }
}
