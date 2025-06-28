<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DineroBaseResource\Pages;
use App\Models\DineroBase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Spatie\Permission\Models\Role;
use App\Filament\Resources\DineroBaseResource\Widgets\DineroBaseTotal;

// Importar los filtros y acciones necesarios para SoftDeletes
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\SoftDeletingScope; // Importar SoftDeletingScope

class DineroBaseResource extends Resource
{
    protected static ?string $model = DineroBase::class;

    protected static ?string $navigationIcon = 'phosphor-bank';
    protected static ?string $navigationGroup = 'Registros';
    protected static ?string $navigationLabel = 'Dinero Base';
    protected static ?string $label = 'Dinero Base';
    protected static ?string $pluralLabel = 'Dinero Base';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Usuario')
                    ->searchable()
                    ->required()
                    ->preload()
                    ->disabled(fn ($record) => $record !== null)
                    ->options(function (?DineroBase $record) {
                        $query = \App\Models\User::query()
                            ->where(function ($query) use ($record) {
                                $query->whereDoesntHave('dineroBase');
                                if ($record?->user_id) {
                                    $query->orWhere('id', $record->user_id);
                                }
                            });

                        return $query->pluck('name', 'id');
                    })
                    ->rules(function (?DineroBase $record) {
                        $ignoreId = $record?->id ?? null;
                        return [
                            'unique:dinero_bases,user_id' . ($ignoreId ? ",$ignoreId" : ''),
                        ];
                    }),
                Forms\Components\TextInput::make('monto_inicial')
                    ->label('Monto inicial')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('monto_general')
                    ->label('Monto capital')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('dinero_en_mano')
                    ->label('Dinero en Caja')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('monto')
                    ->label('Dinero en mano')
                    ->numeric()
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.roles.name')
                    ->label('Rol')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : $state)
                    ->colors([
                        'success' => 'admin',
                        'info' => 'oficina',
                        'warning' => 'agente',
                    ])
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('monto_inicial')
                    ->label('Monto inicial')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('monto_general')
                    ->label('Monto Capital')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('dinero_en_mano')
                    ->label('Dinero en Caja')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('monto')
                    ->label('Dinero en mano')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.'))
                    ->searchable()
                    ->sortable(),
                // Nueva columna para ver la fecha de eliminación (opcional, pero útil)
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Eliminado en')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true), // Oculta por defecto, se puede mostrar
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Filtrar por Rol')
                    ->options(Role::pluck('name', 'name')->toArray())
                    ->query(fn (Builder $query, $data) => $data['value']
                        ? $query->whereHas('user.roles', fn (Builder $q) => $q->where('name', $data['value']))
                        : $query),

                // Nuevo filtro para SoftDeletes
                TrashedFilter::make(), // Este filtro por defecto tiene 3 opciones: 'Not trashed', 'Trashed', 'All'
            ])
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('agregar_dinero')
                    ->label('Agregar Dinero')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('monto')
                            ->label('Monto a agregar')
                            ->numeric()
                            ->required()
                            ->placeholder('Puede agregar valores negativos o positivos'),
                    ])
                    ->action(function (array $data, DineroBase $record): void {
                        $montoAAgregar = (float) $data['monto'];
                        $record->monto += $montoAAgregar;
                        $record->monto_general += $montoAAgregar;

                        // Solo suma a monto_inicial si el monto a agregar es positivo
                        if ($montoAAgregar > 0) {
                            $record->monto_inicial += $montoAAgregar;
                        }

                        $record->save();

                        \Filament\Notifications\Notification::make() // NOSONAR
                            ->title('Monto actualizado correctamente')
                            ->success()
                            ->send();
                    }),
                // Acciones de Restauración y Eliminación Permanente
                Tables\Actions\RestoreAction::make(), // Para restaurar un registro individual
                Tables\Actions\ForceDeleteAction::make(), // Para eliminar un registro individual permanentemente
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(), // Eliminar registros lógicamente
                    Tables\Actions\RestoreBulkAction::make(), // Restaurar registros masivamente
                    Tables\Actions\ForceDeleteBulkAction::make(), // Eliminar registros masivamente permanentemente
                ]),
            ]);
    }

    // Sobrescribir el método getEloquentQuery para incluir los soft deletes en la consulta base
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class, // Asegura que el filtro TrashedFilter funcione correctamente
            ]);
    }


    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDineroBases::route('/'),
            'create' => Pages\CreateDineroBase::route('/create'),
            'edit' => Pages\EditDineroBase::route('/{record}/edit'),
        ];
    }

}