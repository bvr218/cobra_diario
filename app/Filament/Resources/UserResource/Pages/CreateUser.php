<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Spatie\Permission\Models\Role;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Creamos el record y luego sincronizamos el rol singular.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $record = parent::handleRecordCreation($data);
        // Sincronizar único rol
        $role = Role::find($data['role']);
        if ($role) {
            $record->syncRoles($role->name);
        }
        return $record;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $authUser = auth()->user();

        if ($authUser->hasRole('oficina')) {
            $data['oficina_id'] = $authUser->id;

            // Forzamos el rol a 'agente' sin importar lo que llegue del form (seguridad extra)
            $data['role'] = \Spatie\Permission\Models\Role::where('name', 'agente')->value('id');
        }

        return $data;
    }

}
