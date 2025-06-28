<?php

namespace App\Observers;

use App\Models\DineroBase;
use App\Models\HistorialMovimiento;
use Illuminate\Support\Carbon; // Asegúrate de que Carbon esté importado

class DineroBaseObserver
{
    /**
     * Handle the DineroBase "updated" event.
     */
    public function updated(DineroBase $dineroBase): void
    {
        $dirtyAttributes = $dineroBase->getDirty();
        $originalAttributes = $dineroBase->getOriginal();

        // Verificar si solo 'monto' y 'dinero_en_mano' cambiaron (transferencia)
        $isTransferOperation = count($dirtyAttributes) === 2 &&
                               array_key_exists('monto', $dirtyAttributes) &&
                               array_key_exists('dinero_en_mano', $dirtyAttributes);

        if ($isTransferOperation) {
            $originalMonto = $dineroBase->getOriginal('monto');
            $nuevoMonto = $dineroBase->monto;
            $originalDineroEnMano = $dineroBase->getOriginal('dinero_en_mano');
            $nuevoDineroEnMano = $dineroBase->dinero_en_mano;

            $this->registrarMovimiento(
                $dineroBase->user_id,
                'transferencia_caja_mano',
                $nuevoMonto - $originalMonto, // Impacto en 'monto' (caja)
                ['monto' => $originalMonto, 'dinero_en_mano' => $originalDineroEnMano],
                ['monto' => $nuevoMonto, 'dinero_en_mano' => $nuevoDineroEnMano],
                "Transferencia entre Caja y Dinero en Mano. Usuario ID: {$dineroBase->user_id}"
            );
        } elseif ($dineroBase->isDirty('monto')) { // Otro tipo de cambio solo en 'monto'
            $originalMonto = $dineroBase->getOriginal('monto');
            $nuevoMonto = $dineroBase->monto;
            $this->registrarMovimiento(
                $dineroBase->user_id,
                'ajuste_caja', // Tipo específico para ajuste de caja
                $nuevoMonto - $originalMonto,
                ['monto' => $originalMonto],
                ['monto' => $nuevoMonto],
                "Ajuste de Dinero en Caja. Usuario ID: {$dineroBase->user_id}"
            );
        }
        // Aquí puedes añadir lógica para cambios en 'monto_general', 'monto_inicial' si se manejan por separado
        // y no son parte de la operación de transferencia 'caja <=> mano'.
    }

    /**
     * Registra en HistorialMovimiento un único registro para DineroBase.
     *
     * @param int $userId
     * @param string $tipo_accion 'creacion', 'edicion', 'eliminacion', 'transferencia_caja_mano', 'ajuste_caja'
     * @param float $montoPrincipal Monto del cambio principal (ej. en 'monto' o 'dinero_en_mano')
     * @param array $originales Valores originales de los campos cambiados.
     * @param array $nuevos Valores nuevos de los campos cambiados.
     * @param string $descripcionPersonalizada Descripción del movimiento.
     */
    protected function registrarMovimiento(
        int $userId,
        string $tipo_accion,
        float $montoPrincipal,
        array $originales = [],
        array $nuevos = [],
        string $descripcionPersonalizada
    ): void {
        $data = [
            'user_id'       => $userId,
            'tipo'          => $tipo_accion,
            'descripcion'   => $descripcionPersonalizada,
            'monto'         => $montoPrincipal,
            'referencia_id' => $userId, // Se usa el user_id como referencia principal
            'tabla_origen'  => 'dinero_bases',
            'es_edicion'    => in_array($tipo_accion, ['transferencia_caja_mano', 'ajuste_caja']), // O cualquier otro tipo que sea edición
            'fecha'         => now(),
        ];

        if (!empty($originales)) {
            $data['cambio_desde'] = json_encode($originales);
        }
        if (!empty($nuevos)) {
            $data['cambio_hacia'] = json_encode($nuevos);
        }

        HistorialMovimiento::create($data);
    }

    /**
     * Handle the DineroBase "created" event.
     */
    public function created(DineroBase $dineroBase): void
    {
        $this->registrarMovimiento(
            $dineroBase->user_id,
            'creacion',
            $dineroBase->monto, // Monto principal (caja)
            [], // No hay 'originales' específicos para el JSON, pero el registro es de creación
            [ // 'nuevos' serían los valores iniciales
                'monto' => $dineroBase->monto,
                'monto_general' => $dineroBase->monto_general,
                'monto_inicial' => $dineroBase->monto_inicial,
                'dinero_en_mano' => $dineroBase->dinero_en_mano,
            ],
            "Creación de registro Dinero Base. Usuario ID: {$dineroBase->user_id}"
        );
    }

    /**
     * Handle the DineroBase "deleted" event.
     */
    public function deleted(DineroBase $dineroBase): void
    {
        $this->registrarMovimiento(
            $dineroBase->user_id,
            'eliminacion',
            -$dineroBase->getOriginal('monto'), // Impacto negativo en caja
            $dineroBase->getOriginal(), // Todos los valores originales
            [], // No hay 'nuevos'
            "Eliminación de registro Dinero Base. Usuario ID: {$dineroBase->user_id}"
        );
    }

    public function restored(DineroBase $dineroBase): void {}
    public function forceDeleted(DineroBase $dineroBase): void {}
}