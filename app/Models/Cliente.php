<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;


class Cliente extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'nombre',
        'foto_cliente',
        'numero_cedula',
        'telefonos',
        'recomendado',
        'ciudad',
        'direccion',
        'coordenadas',
        'galeria',
        'registrado_por',
        'oficina_id',
        'descripcion',

    ];

    protected $casts = ["galeria"=>"array","telefonos"=>"array"];

    protected $appends = ["reputacion"];

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }

    public function oficina(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oficina_id');
    }

    public function getPrestamo($date)
    {
        $date = $date instanceof Carbon ? $date : Carbon::parse($date ?? now());

        foreach($this->prestamos as $prestamo){
            // Validar que next_payment no sea null
            if ($prestamo->next_payment && $prestamo->deuda_actual > 0) {
                if (Carbon::parse($prestamo->next_payment)->toDateString() == $date->toDateString()) {
                    return $prestamo;
                }
            }
        }

        return null;
    }


    public function prestamos(): HasMany
    {
    return $this->hasMany(Prestamo::class, 'cliente_id');
    }


    public function getReputacionAttribute()
    {
        $total_score = 0;
        $numero_abonos_considerados = 0;
        $default_reputation = 3; // Reputación neutral si no hay abonos o préstamos

        // Es buena idea cargar las relaciones para evitar N+1 queries si usas esto en una colección
        $this->loadMissing('prestamos.abonos'); // Descomentar si es necesario

        if ($this->prestamos->isEmpty()) {
            return $default_reputation;
        }

        foreach ($this->prestamos as $prestamo) {
            if ($prestamo->abonos->isEmpty()) {
                continue; // Si un préstamo no tiene abonos, no afecta la reputación aún
            }

            foreach ($prestamo->abonos as $abono) {
                $score_abono = 0;

                // Validar que los montos no sean null para evitar errores
                $monto_abono_real = (float) $abono->monto_abono;
                $monto_pagado_debido = (float) $abono->monto_pagado;

                // 1. Comparación de montos
                if ($monto_pagado_debido > 0) { // Evitar división por cero o lógica extraña si no debía pagar
                    if ($monto_abono_real >= $monto_pagado_debido) {
                        $score_abono += 1; // Pagó lo debido o más
                    } else {
                        $score_abono -= 1; // Pagó menos
                    }
                }

                if ($abono->fecha_abono && $abono->fecha_pagado) {
                    $fecha_pago_real = Carbon::parse($abono->fecha_abono)->startOfDay();
                    $fecha_pago_debido = Carbon::parse($abono->fecha_pagado)->startOfDay();

                    if ($fecha_pago_real->lte($fecha_pago_debido)) { // lte = Less Than or Equal
                        $score_abono += 1; // Pagó a tiempo o adelantado
                    } else {
                        $score_abono -= 1; // Pagó tarde
                    }
                } else {
                    // Si faltan fechas, podríamos penalizar o ignorar este aspecto.
                    // Por ahora, no sumamos ni restamos si falta alguna fecha crucial.
                    // O podrías decidir $score_abono -= 0.5; por datos incompletos.
                }
                
                $total_score += $score_abono;
                $numero_abonos_considerados++;
            }
        }

        if ($numero_abonos_considerados === 0) {
            return $default_reputation; // No hay abonos para evaluar
        }
        $average_score = $total_score / $numero_abonos_considerados;
        $reputacion_calculada = $average_score + 3;
        $reputacion_final = round($reputacion_calculada);
        
        return max(1, min(5, (int)$reputacion_final));
    }


    public function getPagosAtrasadosAttribute(): int
    {
        $cuotasAtrasadasTotales = 0;
        $hoy = Carbon::today(); // Fecha actual, al inicio del día para comparaciones

        // Cargar préstamos con sus frecuencias para evitar N+1 queries
        $this->loadMissing(['prestamos' => function ($query) {
            $query->with('frecuencia'); // Asegúrate de que la relación 'frecuencia' esté definida en Prestamo
        }]);

        foreach ($this->prestamos as $prestamo) {
            if ($prestamo->deuda_actual > 0 && $prestamo->next_payment && $prestamo->frecuencia && $prestamo->frecuencia->dias > 0) {
                
                $nextPaymentDate = $prestamo->next_payment->copy()->startOfDay(); // Ya debería ser Carbon y startOfDay desde el accesor

                // Si la próxima fecha de pago esperada es anterior a hoy
                if ($nextPaymentDate->lt($hoy)) {
                    // Calcular cuántos días han pasado desde la fecha de pago esperada
                    $diasDeAtraso = $hoy->diffInDays($nextPaymentDate);
                    
                    // Calcular cuántos ciclos de pago completos han pasado
                    // sumamos frecuencia->dias -1 a los dias de atraso para que
                    // si esta atrasado 1 dia y la frecuencia es 7, (1+7-1)/7 = 1
                    // si esta atrasado 7 dias y la frecuencia es 7, (7+7-1)/7 = 1.85 -> floor 1
                    // Para que cuente correctamente el primer ciclo de atraso.
                    // Ejemplo: next_payment era ayer, frecuencia 7 días. Atraso = 1 día. floor((1)/7) = 0. Incorrecto. Debería ser 1 cuota.
                    // Ejemplo: next_payment era hace 7 días, frecuencia 7 días. Atraso = 7 días. floor((7)/7) = 1. Correcto.
                    // Ejemplo: next_payment era hace 8 días, frecuencia 7 días. Atraso = 8 días. floor((8)/7) = 1. Incorrecto. Debería ser 2 cuotas (la de hace 7 días, y la de "hoy" que también pasó).

                    // La forma correcta es calcular cuántas "fechas de pago" han ocurrido entre $nextPaymentDate y $hoy
                    $fechaTemporal = $nextPaymentDate->copy();
                    $cuotasAtrasadasPrestamo = 0;
                    while($fechaTemporal->lt($hoy)) {
                        $cuotasAtrasadasPrestamo++;
                        $fechaTemporal->addDays($prestamo->frecuencia->dias);
                    }
                    $cuotasAtrasadasTotales += $cuotasAtrasadasPrestamo;
                }
            }
        }
        return (int) $cuotasAtrasadasTotales;
    }

    /**
     * Calcula el número de pagos realizados con adelanto.
     * (No incluye pagos hechos exactamente en la fecha debida, solo antes)
     */
    public function getPagosAdelantadosAttribute(): int
    {
        $pagosAdelantados = 0;
        $this->loadMissing('prestamos.abonos'); // Asegurar que las relaciones están cargadas

        foreach ($this->prestamos as $prestamo) {
            foreach ($prestamo->abonos as $abono) {
                if ($abono->fecha_abono && $abono->fecha_pagado) {
                    $fechaPagoReal = Carbon::parse($abono->fecha_abono)->startOfDay();
                    $fechaPagoDebido = Carbon::parse($abono->fecha_pagado)->startOfDay();

                    if ($fechaPagoReal->lt($fechaPagoDebido)) { // lt = Less Than
                        $pagosAdelantados++;
                    }
                }
            }
        }
        return $pagosAdelantados;
    }

    public function getPagosAtrasadosTotalesAttribute(): int
    {
        $pagosAtrasados = 0;
        $this->loadMissing('prestamos.abonos'); // Asegurar que las relaciones están cargadas

        foreach ($this->prestamos as $prestamo) {
            foreach ($prestamo->abonos as $abono) {
                if ($abono->fecha_abono && $abono->fecha_pagado) {
                    $fechaPagoReal = Carbon::parse($abono->fecha_abono)->startOfDay();
                    $fechaPagoDebido = Carbon::parse($abono->fecha_pagado)->startOfDay();

                    if ($fechaPagoReal->gt($fechaPagoDebido)) { // gt = Greater Than
                        $pagosAtrasados++;
                    }
                }
            }
        }
        return $pagosAtrasados;
    }

}
