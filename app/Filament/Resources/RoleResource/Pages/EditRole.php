<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;
use App\Helpers\HistorialHelper;
use Illuminate\Support\Facades\DB;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected array $oldPermissions = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn () => $this->record->name !== 'admin'),
        ];
    }

    protected function canEdit(): bool
    {
        return $this->record->name !== 'admin';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->name === 'admin') {
            Notification::make()
                ->title('Este rol no puede ser editado.')
                ->body('El rol "admin" está protegido y no puede ser modificado.')
                ->danger()
                ->duration(5000)
                ->send();

            $this->halt();
            return $this->data;
        }

        // Capturar permisos actuales desde la base antes de guardar
        $this->oldPermissions = $this->record->permissions()->pluck('name')->sort()->values()->toArray();

        return $data;
    }

    protected function saved(): void
    {
        // Recargar relación actualizada después del guardado
        $this->record->load('permissions');
        $newPermissions = $this->record->permissions->pluck('name')->sort()->values()->toArray();

        // Comparar los arrays ordenados
        if ($this->oldPermissions !== $newPermissions) {
            HistorialHelper::registrar([
                'tipo' => 'edición',
                'descripcion' => 'Permisos modificados para el rol "' . $this->record->name . '"',
                'es_edicion' => true,
                'cambio_desde' => json_encode($this->oldPermissions),
                'cambio_hacia' => json_encode($newPermissions),
                'referencia_id' => $this->record->id,
                'tabla_origen' => 'roles',
            ]);
        }
    }
}
