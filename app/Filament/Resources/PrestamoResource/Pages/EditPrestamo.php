<?php

namespace App\Filament\Resources\PrestamoResource\Pages;

use App\Filament\Resources\PrestamoResource;
use App\Models\Cliente;
use Filament\Resources\Pages\EditRecord;
use Filament\Pages\Actions\DeleteAction;
use Illuminate\Support\Facades\Auth;

class EditPrestamo extends EditRecord
{
    protected static string $resource = PrestamoResource::class;

    /**
     * Aquí definimos las acciones que aparecerán en la parte superior derecha de la página de edición.
     * Agregamos DeleteAction solo si el préstamo NO tiene abonos.
     */
    protected function getActions(): array
    {
        // Obtenemos el modelo Prestamo que estamos editando
        /** @var \App\Models\Prestamo $prestamo */
        $prestamo = $this->record;

        // Si NO hay ningún abono para este préstamo, mostramos el DeleteAction
        if ($prestamo->abonos()->count() === 0) {
            return [
                DeleteAction::make()
                    ->label('Eliminar Préstamo')
                    ->requiresConfirmation()
                    // Personaliza el mensaje del modal de confirmación si lo deseas
                    ->modalHeading('¿Eliminar este préstamo?')
                    ->modalSubheading('Esta acción borrará el préstamo permanentemente y no se puede deshacer.')
                    // Definimos la redirección y la notificación tras eliminar
                    ->successRedirectUrl($this->getResource()::getUrl('index'))
                    ->successNotificationTitle('Préstamo eliminado')
                    // Opcional: puedes personalizar el cuerpo de la notificación
                    // ->successNotificationBody('El préstamo ha sido borrado correctamente.')
            ];
        }

        // Si hay al menos un abono, no devolvemos ninguna acción (no aparece “Eliminar”)
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = Auth::user();

        // Si el usuario no tiene permisos para ver el selector de 'registrado_id',
        // el campo no vendrá en $data. Debemos manejar su valor aquí.
        if (!($user->can('prestamos.index') || $user->can('prestamosOficina.index'))) {
            // El cliente pudo haber sido cambiado en el formulario.
            $newClientId = $data['cliente_id'];
            $originalClientId = $this->record->cliente_id;

            if ($newClientId != $originalClientId) {
                // Si el cliente cambió, actualizamos 'registrado_id' basado en el nuevo propietario del cliente.
                $cliente = Cliente::find($newClientId);
                $data['registrado_id'] = $cliente?->registrado_por ?? $this->record->registrado_id;
            } else {
                // Si el cliente no cambió, el 'registrado_id' original debe ser preservado.
                // Lo agregamos de nuevo a los datos para evitar que se guarde como nulo.
                $data['registrado_id'] = $this->record->registrado_id;
            }
        }

        return $data;
    }
}
