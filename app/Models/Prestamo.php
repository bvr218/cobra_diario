<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; // Añadido para DB::transaction si no estaba explícito

class Prestamo extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'cliente_id',
        'agente_asignado',
        'frecuencia_id',
        'initial_date',
        'estado',
        'valor_total_prestamo',
        'interes',
        'numero_cuotas',
        'comicion',
        'registrado_id',
        'posicion_ruta',
        'cuotas_restantes',
        'descuento_aplicado',
        'valor_prestado_con_interes',
        'monto_general_aplicado',
        'comicion_borrada',
        'deuda_inicial', // Este campo se calculará con la nueva lógica
    ];

    protected $casts = [
        'valor_total_prestamo' => 'integer',
        'interes' => 'integer',
        'cuotas_restantes'       => 'integer',
        'monto_general_aplicado' => 'boolean',
        'valor_prestado_con_interes' => 'integer',
        'comicion_borrada'       => 'boolean',
        'deuda_inicial'          => 'integer',
        'initial_date'           => 'datetime', // Asegurar que initial_date sea Carbon
    ];

    protected $appends = [
        'refinancing_message',
    ];

    public function setValorPrestadoConInteresAttribute($value)
    {
        $principal = $this->attributes['valor_total_prestamo'] ?? 0;
        $tasa = ($this->attributes['interes'] ?? 0) / 100;
        $this->attributes['valor_prestado_con_interes'] = (int) round($principal + ($principal * $tasa));
    }

    protected static function booted()
    {
        static::saving(function (Prestamo $prestamo) {
            $originalAgentId = $prestamo->getOriginal('agente_asignado');
            $prestamo->setValorPrestadoConInteresAttribute(null);

            // --- Lógica para el cálculo de deuda_inicial (AQUÍ ES DONDE APLICA EL CAMBIO) ---
            // Busca el último refinanciamiento autorizado
            $ultimoRefinanciamientoAutorizado = $prestamo->refinanciamientos()
                                                          ->where('estado', 'autorizado')
                                                          ->orderByDesc('id') // Asumiendo que el ID descendente indica el último
                                                          ->first();

            if ($ultimoRefinanciamientoAutorizado) {
                // Si hay un refinanciamiento autorizado, la deuda_inicial es la deuda_refinanciada_interes de ese refinanciamiento
                $prestamo->deuda_inicial = (int) round($ultimoRefinanciamientoAutorizado->deuda_refinanciada_interes);
            } else {
                // Si no hay refinanciamientos autorizados, la deuda_inicial es el valor_prestado_con_interes original
                $prestamo->deuda_inicial = (int) round($prestamo->valor_prestado_con_interes);
            }
            // --- FIN Lógica para el cálculo de deuda_inicial ---


            // --- Logic for posicion_ruta based on agente_asignado changes ---
            if ($prestamo->isDirty('agente_asignado')) {
                $newAgentId = $prestamo->agente_asignado;

                if (!is_null($newAgentId) && $newAgentId != $originalAgentId) {
                    \DB::transaction(function () use ($prestamo, $newAgentId) {
                        Prestamo::where('agente_asignado', $newAgentId)
                            ->where('id', '!=', $prestamo->id)
                            ->where('estado', '!=', 'finalizado')
                            ->increment('posicion_ruta');
                        $prestamo->posicion_ruta = 1;
                    });
                }

                if (!is_null($originalAgentId) && $newAgentId != $originalAgentId) {
                    \DB::transaction(function () use ($originalAgentId) {
                        $loansToReorder = Prestamo::where('agente_asignado', $originalAgentId)
                            ->where('estado', '!=', 'finalizado')
                            ->orderBy('posicion_ruta', 'asc')
                            ->get();

                        foreach ($loansToReorder as $index => $loanToReorder) {
                            if ($loanToReorder->posicion_ruta != ($index + 1)) {
                                $loanToReorder->posicion_ruta = $index + 1;
                                $loanToReorder->saveQuietly();
                            }
                        }
                    });
                }

                if (is_null($newAgentId) && !is_null($originalAgentId)) {
                    $prestamo->posicion_ruta = 0; // Mark as un-routed
                }
            }
            // --- End of posicion_ruta logic ---

            // --- Lógica de transición de estado AUTOMÁTICA y RESTRICCIONES MANUALES ---
            $originalEstado = $prestamo->getOriginal('estado');
            $nuevoEstado = $prestamo->estado;

            // Transiciones permitidas desde 'pendiente'
            $allowedFromPendiente = ['negado', 'activo', 'autorizado'];

            // Estados que pueden transicionar a 'finalizado' (y solo a 'finalizado')
            $statesThatCanOnlyGoToFinalizado = ['activo', 'autorizado'];

            // Validación de cambio de estado
            if ($prestamo->isDirty('estado')) {
                // Caso 1: Intentando cambiar de 'pendiente' a un estado no permitido
                if ($originalEstado === 'pendiente' && !in_array($nuevoEstado, $allowedFromPendiente)) {
                    $prestamo->estado = $originalEstado;
                    throw ValidationException::withMessages([
                        'estado' => "Un préstamo 'pendiente' solo puede cambiar a 'negado', 'activo' o 'autorizado'."
                    ]);
                }
                // Caso 2: Intentando cambiar de 'activo' o 'autorizado'
                elseif (in_array($originalEstado, $statesThatCanOnlyGoToFinalizado)) {
                    // Si el nuevo estado NO es 'finalizado'
                    if ($nuevoEstado !== 'finalizado') {
                        // Se permite la transición entre 'activo' y 'autorizado'
                        if (($originalEstado === 'activo' && $nuevoEstado === 'autorizado') ||
                            ($originalEstado === 'autorizado' && $nuevoEstado === 'activo')) {
                            // Permitido: no hacer nada, la transición es válida.
                        } else {
                            // Cualquier otro cambio desde 'activo' o 'autorizado' (que no sea 'finalizado'
                            // ni entre ellos) es inválido.
                            $prestamo->estado = $originalEstado;
                            throw ValidationException::withMessages([
                                'estado' => "Un préstamo '{$originalEstado}' solo puede cambiar a 'finalizado', 'activo' o 'autorizado'."
                            ]);
                        }
                    }
                    // Si el nuevo estado ES 'finalizado', verificar la deuda
                    else { // $nuevoEstado === 'finalizado'
                        // Accedemos al accesor `deuda_actual` aquí.
                        // Esto garantiza que la validación use el valor calculado con las refinanciaciones autorizadas.
                        if ($prestamo->deuda_actual > 0) {
                            $prestamo->estado = $originalEstado;
                            throw ValidationException::withMessages([
                                'estado' => 'El estado de un préstamo activo o autorizado solo puede pasar a "Finalizado" cuando la deuda actual sea 0.'
                            ]);
                        }
                        // Si la deuda es 0, la transición a 'finalizado' es válida, la lógica posterior la manejará
                    }
                }
            }

            // Lógica para que el estado se pase AUTOMÁTICAMENTE a finalizado cuando la deuda es 0
            // Importante: Esto usará el accesor `deuda_actual`
            if ($prestamo->deuda_actual <= 0) {
                if ($prestamo->estado !== 'finalizado') {
                    $prestamo->estado = 'finalizado'; // Forzar a finalizado
                }
                // Si el préstamo se finaliza, desasignar al agente y resetear posición de ruta
                if (!is_null($prestamo->agente_asignado)) {
                    $agentToReorderPostFinalization = $prestamo->agente_asignado;
                    $prestamo->agente_asignado = null; // Desasignar
                    $prestamo->posicion_ruta = 0;   // Resetear posición

                    // Reordenar la ruta del agente del que se finaliza el préstamo
                    if (!is_null($agentToReorderPostFinalization)) {
                        \DB::transaction(function () use ($agentToReorderPostFinalization) {
                            $loansToReorder = Prestamo::where('agente_asignado', $agentToReorderPostFinalization)
                                ->where('estado', '!=', 'finalizado')
                                ->orderBy('posicion_ruta', 'asc')
                                ->get();
                            foreach ($loansToReorder as $index => $loanToReorder) {
                                if ($loanToReorder->posicion_ruta != ($index + 1)) {
                                    $loanToReorder->posicion_ruta = $index + 1;
                                    $loanToReorder->saveQuietly();
                                }
                            }
                        });
                    }
                }
            } else { // Si la deuda actual es > 0
                // Si el préstamo estaba en finalizado y la deuda vuelve a ser > 0 (por ejemplo, por una reversión o edición)
                // lo regresamos a 'activo'.
                if ($prestamo->estado === 'finalizado') {
                    $prestamo->estado = 'activo';
                }
            }
            // --- FIN Lógica de transición de estado y restricciones ---
        });

        // Evento para restringir la eliminación
        static::deleting(function (Prestamo $prestamo) {
            if ($prestamo->abonos()->count() > 0) {
                throw ValidationException::withMessages([
                    'delete' => 'No se puede eliminar un préstamo que ya tiene abonos registrados.'
                ]);
            }
        });
    }

    // --- Relaciones ---

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function frecuencia(): BelongsTo
    {
        return $this->belongsTo(Frecuencia::class, 'frecuencia_id');
    }

    public function agenteAsignado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agente_asignado');
    }

    public function abonos(): HasMany
    {
        return $this->hasMany(Abono::class);
    }

    public function refinanciamientos(): HasMany
    {
        return $this->hasMany(Refinanciamiento::class);
    }

    public function promesas(): HasMany
    {
        return $this->hasMany(Promesa::class);
    }

    public function registrado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_id');
    }

    // --- Accesores ---

    /**
     * Devuelve el interés del préstamo, priorizando el de la última refinanciación autorizada.
     */
    public function getInteresAttribute()
    {
        // Solo considera refinanciaciones autorizadas
        $ref = $this->refinanciamientos()->where('estado', 'autorizado')->orderBy("id","desc")->first();
        if(is_null($ref)){
            return $this->attributes['interes']; // Retorna el interés original del préstamo
        }
        return $ref->interes; // Retorna el interés de la última refinanciación autorizada
    }

    /**
     * Calcula la deuda actual del préstamo, incluyendo solo refinanciaciones autorizadas.
     */
    public function getDeudaActualAttribute(): float
    {
        // Usamos el interés original del préstamo para calcular la base del préstamo inicial.
        $principalOriginalPrestamo = $this->valor_total_prestamo ?? 0;
        $tasaOriginalPrestamo = ($this->attributes['interes'] ?? 0) / 100; // Accede directamente al atributo 'interes' de la BD

        $totalConInteresInicial = $principalOriginalPrestamo + ($principalOriginalPrestamo * $tasaOriginalPrestamo);

        // Suma de abonos que reducen la deuda
        $abonosTotales = $this->abonos()->sum('monto_abono') ?? 0;

        // Suma de los 'total' de las refinanciaciones, PERO SOLO LAS AUTORIZADAS
        $refinanciacionesAutorizadasTotal = $this->refinanciamientos()
                                                   ->where('estado', 'autorizado')
                                                   ->sum('total') ?? 0;

        // La deuda actual es el (total inicial del préstamo + total de refinanciaciones autorizadas) - abonos totales
        $deudaCalculada = $totalConInteresInicial + $refinanciacionesAutorizadasTotal - $abonosTotales;

        // Asegura que la deuda no sea negativa
        return max($deudaCalculada, 0);
    }


    public function getNextPaymentAttribute()
    {
        if ($this->deuda_actual <= 0) {
            return null;
        }

        if ($this->abonos->count() > 0) {
            $promesa = $this->promesas()->where("to_pay", ">=", date("Y-m-d"))->first();
            if (!is_null($promesa)) {
                return Carbon::parse($promesa->to_pay);
            }

            $lastAbono = $this->abonos()->orderByDesc('fecha_abono')->first();
            return Carbon::parse($lastAbono->fecha_abono)->addDays($this->frecuencia->dias);
        }

        return Carbon::parse($this->initial_date);
    }

    public function getIsPromesaAttribute()
    {
        $promesa = $this->promesas()->where("to_pay",">",date("Y-m-d"))->first();
        if(!is_null($promesa)){
            return true;
        }
        return false;
    }

    public function getMontoPorCuotaAttribute(): float
    {
        // Ahora MontoPorCuota usará la deuda_actual calculada por el accesor
        $principal = $this->deuda_actual ?? 0;
        $cuotas = $this->numero_cuotas ?? 0;

        if ($cuotas === 0) {
            return 0;
        }

        return $principal / $cuotas;
    }

    /**
     * Get the refinancing message for the loan.
     * Este es el nuevo accessor que solicitaste.
     *
     * @return string
     */
    public function getRefinancingMessageAttribute(): string
    {
        // Priorizamos la revisión del estado "negado" ya que debe ser visible para todos.
        if ($this->refinanciamientos()->where('estado', 'negado')->exists()) {
            return '<span style="color: #EF4444; font-weight: 600; font-size: 0.875rem;">🛑 No refinanciable</span>';
        }

        // Si el usuario no está autenticado O tiene el permiso 'prestamos.index',
        // entonces no mostramos el mensaje "En espera de autorización".
        // La lógica para "No refinanciable" ya se manejó arriba.
        if (!Auth::check() || Auth::user()->can('prestamos.index')) {
            return '';
        }

        // Si el usuario NO tiene permiso 'prestamos.index' Y NO hay refinanciación negada,
        // entonces revisamos si hay una pendiente.
        if ($this->refinanciamientos()->where('estado', 'pendiente')->exists()) {
            return '<span style="color:rgb(90, 36, 238); font-weight: 600; font-size: 0.875rem;">⏰ En espera de autorización.</span>';
        }

        // En cualquier otro caso (no hay negado ni pendiente, o el usuario es admin), devolvemos cadena vacía
        return '';
    }
}