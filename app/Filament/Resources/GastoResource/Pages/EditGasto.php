<?php

namespace App\Filament\Resources\GastoResource\Pages;

use App\Filament\Resources\GastoResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification;

class EditGasto extends EditRecord
{
    protected static string $resource = GastoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Show the delete action on the edit page only if the user has 'gastos.delete' permission
            Actions\DeleteAction::make()
                ->visible(fn (): bool => auth()->user()->can('gastos.delete')),
        ];
    }

    protected function beforeSave(): void
    {
        // If the expense is authorized and the user DOES NOT have 'gastos.index'
        // or 'gastosOficina.index' permission, block the edit.
        if ($this->record->autorizado && !auth()->user()->can('gastos.index') && !auth()->user()->can('gastosOficina.index')) {
            Notification::make()
                ->title('Este gasto ya fue autorizado y no puede modificarse.')
                ->danger()
                ->send();

            $this->halt();
        }
    }
}