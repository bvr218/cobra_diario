<?php

namespace App\Observers;

use App\Helpers\HistorialHelper;
use Illuminate\Database\Eloquent\Model;

class GenericObserver
{
    public function created(Model $model): void
    {
        HistorialHelper::registrar([
            'tipo' => 'creación',
            'descripcion' => 'Registro creado en ' . class_basename($model),
            'monto' => $model->monto ?? 0,
            'referencia_id' => $model->id,
            'tabla_origen' => $model->getTable(),
        ]);
    }

    public function updated(Model $model): void
    {
        HistorialHelper::registrar([
            'tipo' => 'edición',
            'descripcion' => 'Registro editado en ' . class_basename($model),
            'es_edicion' => true,
            'cambio_desde' => json_encode($model->getOriginal()),
            'cambio_hacia' => json_encode($model->getChanges()),
            'monto' => $model->monto ?? 0,
            'referencia_id' => $model->id,
            'tabla_origen' => $model->getTable(),
        ]);
    }

    public function deleted(Model $model): void
    {
        HistorialHelper::registrar([
            'tipo' => 'eliminación',
            'descripcion' => 'Registro eliminado de ' . class_basename($model),
            'monto' => $model->monto ?? 0,
            'cambio_desde' => json_encode($model->getOriginal()),
            'referencia_id' => $model->id,
            'tabla_origen' => $model->getTable(),
        ]);
    }
}
