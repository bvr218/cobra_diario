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
use Illuminate\Support\Facades\DB;
use App\Models\AjusteDinero;
use Filament\Notifications\Notification;

// Importar los filtros y acciones necesarios para SoftDeletes
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\SoftDeletingScope;

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
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Eliminado en')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('roles')
                    ->label('Filtrar por Rol')
                    ->options(Role::pluck('name', 'name')->toArray())
                    ->query(fn (Builder $query, $data) => $data['value']
                        ? $query->whereHas('user.roles', fn (Builder $q) => $q->where('name', $data['value']))
                        : $query),

                // // Filtro para SoftDeletes
                // TrashedFilter::make(),
            ])
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('ajustar_dinero')
                    ->label('Ajustar Dinero')
                    ->icon('heroicon-o-pencil-square')
                    ->color('info')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('monto')
                            ->label('Monto a ajustar')
                            ->numeric()
                            ->required()
                            ->helperText('Usa valores positivos para agregar y negativos para restar.'),
                        Forms\Components\Textarea::make('descripcion')
                            ->label('Descripción del ajuste')
                            ->required()
                            ->minLength(10)
                            ->maxLength(255),
                    ])
                    ->action(function (array $data, DineroBase $record): void {
                        $montoAjuste = (float) $data['monto'];
                        $descripcion = $data['descripcion'];

                        DB::transaction(function () use ($record, $montoAjuste, $descripcion) {
                            // 1. Capturar estado ANTES del cambio
                            $estadoAntes = $record->only(['monto', 'monto_general', 'dinero_en_mano', 'monto_inicial']);

                            // 2. Aplicar el cambio
                            $record->monto += $montoAjuste;
                            $record->monto_general += $montoAjuste;
                            
                            if ($montoAjuste > 0) {
                                $record->monto_inicial += $montoAjuste;
                            }
                            $record->save();

                            // 3. Capturar estado DESPUÉS del cambio
                            $estadoDespues = $record->only(['monto', 'monto_general', 'dinero_en_mano', 'monto_inicial']);

                            // 4. Crear el registro de auditoría
                            AjusteDinero::create([
                                'user_id' => $record->user_id,
                                'ajustado_por_id' => auth()->id(),
                                'dinero_base_antes' => $estadoAntes,
                                'dinero_base_despues' => $estadoDespues,
                                'monto_ajuste' => $montoAjuste,
                                'tipo_ajuste' => $montoAjuste >= 0 ? 'positivo' : 'negativo',
                                'descripcion' => $descripcion,
                            ]);
                        });

                        Notification::make()
                            ->title('Dinero ajustado correctamente')
                            ->success()
                            ->send();
                    }),
                // Acciones de Restauración y Eliminación Permanente
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                //     Tables\Actions\RestoreBulkAction::make(),
                //     Tables\Actions\ForceDeleteBulkAction::make(),
                // ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
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