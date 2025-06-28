<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Actualizamos el record y luego sincronizamos el rol singular.
     */
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        // Obtener el nombre del rol
        $role = Role::find($data['role']);

        if ($role && in_array($role->name, ['oficina', 'admin'])) {
            $data['oficina_id'] = null; // Limpiar oficina si el rol no la necesita
        }

        // Guardar con los datos modificados
        $record = parent::handleRecordUpdate($record, $data);

        // Sincronizar único rol
        if ($role) {
            $record->syncRoles($role->name);
        }

        return $record;
    }
}
