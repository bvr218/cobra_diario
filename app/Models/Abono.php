<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Abono extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'prestamo_id',
        'monto_abono',
        'monto_pagado',
        'fecha_abono',
        'fecha_pagado',
        'numero_cuota',
        'registrado_por_id',
    ];

    protected $casts = [
        'monto_abono' => 'integer',
        'monto_pagado' => 'integer',
    ];

    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class);
    }
    
    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_id');
    }

    public static function boot()
    {

        parent::boot();

        static::creating(function ($abono){
            $maxCuota = self::where('prestamo_id', $abono->prestamo_id)->max('numero_cuota');
            $abono->numero_cuota = $maxCuota ? $maxCuota + 1 : 1;

            $abono->registrado_por_id = $abono->registrado_por_id??auth()->user()->id;

            $abono->fecha_pagado = $abono->prestamo->next_payment;
            $abono->monto_pagado = $abono->prestamo->monto_por_cuota;
        });

        static::updated(function ($abono){
            $abono->registrado_por_id = $abono->registrado_por_id??auth()->user()->id;
            // No es necesario llamar a prestamo->save() aquí si ya lo hacemos en saved()
        });

        static::saved(function (Abono $abono) {
            if ($abono->prestamo) {
                // Dispara el guardado del préstamo asociado para que se
                // ejecuten sus propios eventos 'saving' (donde está la lógica de finalización).
                $abono->prestamo->save();
            }
        });

        static::deleted(function (Abono $abono) {
            if ($abono->prestamo) {
                // También es importante cuando se elimina un abono,
                // ya que la deuda del préstamo cambiará.
                $abono->prestamo->save();
            }
        });
    } 

    // public function getDeudaAnteriorAttribute()
    // {
    //     $prestamo = $this->prestamo;

    //     if (!$prestamo) {
    //         return 0;
    //     }

    //     $valor = $prestamo->valor_total_prestamo;
    //     $cuotas = $prestamo->numero_cuotas;
    //     $interes = $prestamo->interes / 100;

    //     $total = $valor + ($valor * $interes);

    //     $abonosAnteriores = $prestamo->abonos()
    //         ->where('id', '<', $this->id)
    //         ->sum('monto_abono');

    //     return max($total - $abonosAnteriores, 0);
    // }

    public function getDeudaAnteriorAttribute()
    {
        $principal = $this->prestamo->valor_total_prestamo ?? 0;
        $cuotas = $this->prestamo->numero_cuotas ?? 0;
        $tasa = ($this->prestamo->getOriginal('interes') ?? 0) / 100;

        $total = $principal + ($principal * $tasa);
        // $abonos = $this->prestamo->abonos()->sum('monto_abono') ?? 0;

        $abonos = $this->prestamo->abonos()
            ->where('id', '<', $this->id)
            ->sum('monto_abono');

        $refin = $this->prestamo->refinanciamientos()->sum('total') ?? 0;
        return max($total - $abonos + $refin, 0);
    }

    public function getDeudaActualAttribute()
    {
        $principal = $this->prestamo->valor_total_prestamo ?? 0;
        $cuotas = $this->prestamo->numero_cuotas ?? 0;
        $tasa = ($this->prestamo->getOriginal('interes') ?? 0) / 100;

        $total = $principal + ($principal * $tasa);
        $abonos = $this->prestamo->abonos()->sum('monto_abono') ?? 0;
        $refin = $this->prestamo->refinanciamientos()->sum('total') ?? 0;
        return max($total - $abonos + $refin, 0);
    }


    // public function getDeudaActualAttribute()
    // {
    //     $prestamo = $this->prestamo;

    //     if (!$prestamo) {
    //         return 0;
    //     }

    //     $valor = $prestamo->valor_total_prestamo;
    //     $cuotas = $prestamo->numero_cuotas;
    //     $interes = $prestamo->interes / 100;

    //     $total = $valor + ($valor * $interes);

    //     $abonosAnteriores = $prestamo->abonos()
    //     ->where('id', '<=', $this->id)
    //     ->sum('monto_abono');

    //     return max($total - $abonosAnteriores, 0);
    // }

    
}
