<?php

namespace App\Filament\Resources\ClienteResource\Pages;

use App\Filament\Resources\ClienteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditCliente extends EditRecord
{
    protected static string $resource = ClienteResource::class;

    // Configurar acciones del encabezado
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // Método para verificar si el usuario tiene permiso de edición
    protected function canEdit(): bool
    {
        // Verifica si el usuario tiene el permiso 'clientes.view' pero no el de editar
        return auth()->user()->can('clientes.view');
    }

    // Mutar los datos antes de guardarlos
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Verificar si el usuario tiene el permiso de edición
        if (auth()->user()->can('clientes.view')) {
            // Mostrar una notificación de error
            Notification::make()
                ->title('No tienes permiso para editar este cliente.')
                ->body('Solo tienes permiso para ver los clientes, no puedes hacer cambios.')
                ->danger()
                ->duration(5000)
                ->send();

            // Detener la acción de guardado
            $this->halt(); // Detiene la acción sin guardar ni recargar

            return $this->data; // Devuelve los datos sin cambios
        }

        // Si el usuario tiene permiso de edición, continuar con los datos
        return $data;
    }
}
