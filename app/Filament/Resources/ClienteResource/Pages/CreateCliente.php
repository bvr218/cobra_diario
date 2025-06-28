<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCliente extends CreateRecord
{
    protected static string $resource = ClienteResource::class;

    /**
     * Ajusta los datos del formulario antes de crear el registro.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Forzar 'registrado_por' si el campo estaba deshabilitado:
        //   • Si tiene clientes.create + clientes.view (lógica original)
        //   • O si tiene clientesOficina.index (nueva condición)
        if (
            (auth()->user()->can('clientes.create') && auth()->user()->can('clientes.view'))
            || auth()->user()->can('clientesOficina.index')
        ) {
            $data['registrado_por'] = auth()->id();
        }

        // Asignar 'oficina_id' si no puede ver la lista completa de clientes
        if (! auth()->user()->can('clientes.index')) {
            if (auth()->user()->can('clientesOficina.index')) {
                // Usuarios de oficina usan su propio ID como oficina_id
                $data['oficina_id'] = auth()->id();
            } else {
                // Agentes toman la oficina padre de su usuario
                $data['oficina_id'] = auth()->user()->oficina_id;
            }
        }

        return $data;
    }
}
