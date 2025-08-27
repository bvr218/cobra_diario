<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AjusteDinero extends Model
{
    use HasFactory;

    protected $table = 'ajustes_dinero';

    protected $fillable = [
        'user_id',
        'ajustado_por_id',
        'dinero_base_antes',
        'dinero_base_despues',
        'monto_ajuste',
        'tipo_ajuste',
        'descripcion',
    ];

    protected $casts = [
        'dinero_base_antes' => 'array',
        'dinero_base_despues' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function ajustadoPor()
    {
        return $this->belongsTo(User::class, 'ajustado_por_id');
    }
}