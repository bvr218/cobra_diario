<?php

namespace App\Filament\Resources\PrestamoResource\Pages;

use App\Models\Cliente;
use App\Filament\Resources\PrestamoResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePrestamo extends CreateRecord
{
    protected static string $resource = PrestamoResource::class;

    /**
     * Antes de crear un registro, forzamos que el agente_asignado
     * y comicion_borrada vayan en los datos. En este caso, comicion_borrada
     * siempre se inserta como false (el Hidden ya lo pone, pero lo reafirmamos).
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        // Aseguramos que comicion_borrada esté en false:
        $data['comicion_borrada'] = false;

        // Lógica de fallback si los campos no fueron llenados por el formulario
        // (ej. porque estaban ocultos o el afterStateUpdated no se disparó)
        if (!empty($data['cliente_id'])) {
            $cliente = Cliente::find($data['cliente_id']);
            if ($cliente && $cliente->registrado_por) {
                // Si 'registrado_id' no fue enviado en el formulario, lo asignamos desde el cliente.
                if (!isset($data['registrado_id'])) {
                    $data['registrado_id'] = $cliente->registrado_por;
                }

                // Si 'agente_asignado' no fue enviado, lo asignamos desde el cliente.
                // Esto será sobrescrito más abajo si el usuario actual es un agente.
                if (!isset($data['agente_asignado'])) {
                    $data['agente_asignado'] = $cliente->registrado_por;
                }
            }
        }

        // Regla de negocio: Si el usuario que crea es un 'agente', él es el agente asignado.
        // Esto tiene prioridad sobre la asignación del cliente.
        if ($user->hasRole('agente')) {
            $data['agente_asignado'] = $user->id;
        }

        // Fallback final: Si 'registrado_id' sigue vacío, asignamos al usuario actual.
        if (empty($data['registrado_id'])) {
            $data['registrado_id'] = $user->id;
        }

        return $data;
    }
}
