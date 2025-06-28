<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Model;

class TipoGasto extends Model
{

    protected $table = 'tipo_gastos';

    protected $fillable = [
        'nombre',
    ];


    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class);
    }

}
