<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RefinanciamientoResource\Pages;
use App\Filament\Resources\RefinanciamientoResource\RelationManagers;
use App\Models\Refinanciamiento;
use App\Models\Prestamo;
use App\Models\User; // Importamos el modelo User
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Auth;

class RefinanciamientoResource extends Resource
{
    protected static ?string $model = Refinanciamiento::class;

    protected static ?string $navigationIcon = 'tabler-moneybag-plus';

    protected static ?string $navigationGroup = 'Gestión de Préstamos';
    protected static ?string $modelLabel = 'Refinanciamiento';
    protected static ?string $pluralModelLabel = 'Refinanciamientos';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('prestamo_id')
                    ->label('Préstamo Asociado')
                    ->options(Prestamo::all()->mapWithKeys(function ($prestamo) {
                        return [$prestamo->id => 'Préstamo #' . $prestamo->id . ' - ' . ($prestamo->cliente->nombre ?? 'N/A')];
                    }))
                    ->searchable()
                    ->required(),
                Forms\Components\TextInput::make('valor')
                    ->numeric()
                    ->prefix('$')
                    ->required(),
                Forms\Components\TextInput::make('interes')
                    ->numeric()
                    ->suffix('%')
                    ->required(),
                Forms\Components\Select::make('estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'autorizado' => 'Autorizado',
                        'negado' => 'Negado',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('comicion')
                    ->label('Seguro a Cobrar')
                    ->numeric()
                    ->prefix('$')
                    ->nullable()
                    ->helperText('Si este seguro ya fue liquidado, no podrá ser editado')
                    ->disabled(fn ($get) => $get('comicion_borrada'))
                    ->dehydrated(fn ($get) => !$get('comicion_borrada')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('prestamo.cliente.nombre')
                    ->label('Cliente')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('valor')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP')
                    ->label('Valor Entregado')
                    ->sortable(),
                Tables\Columns\TextColumn::make('interes')
                    ->suffix('%')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total')
                    ->label('Valor Con Interes')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('deuda_refinanciada')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP')
                    ->label('Deuda Sin Interes')
                    ->sortable(),
                Tables\Columns\TextColumn::make('deuda_refinanciada_interes')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP')
                    ->label('Deuda Con Interes')
                    ->sortable(),
                Tables\Columns\TextColumn::make('comicion')
                    ->label('Seguro')
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pendiente' => 'warning',
                        'autorizado' => 'success',
                        'negado' => 'danger',
                    })
                    ->sortable()
                    ->toggleable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('prestamo.registrado.name')
                    ->label('Registrado por')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('registrado_id')
                    ->label('Agente') // Cambiado el label para mayor claridad
                    ->relationship(
                        'prestamo.registrado', // La relación se mantiene igual
                        'name',               // La columna a mostrar se mantiene igual
                        fn (Builder $query) => $query->role('agente') // Esto filtra los resultados de la tabla
                    )
                    ->options(function () {
                        // Esta closure es crucial para las opciones del desplegable.
                        // Solo obtenemos los usuarios con el rol 'agente'.
                        return User::role('agente')
                                    ->pluck('name', 'id')
                                    ->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        // Esta closure es para aplicar el filtro real a la tabla.
                        // Solo aplica si se selecciona un usuario en el filtro.
                        if (empty($data['value'])) {
                            return $query;
                        }

                        return $query->whereHas('prestamo.registrado', function (Builder $registradoQuery) use ($data) {
                            $registradoQuery->where('id', $data['value']);
                        });
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('estado')
                    ->options([
                        'pendiente' => 'Pendiente',
                        'autorizado' => 'Autorizado',
                        'negado' => 'Negado',
                    ])
                    ->label('Filtrar por Estado'),
                Tables\Filters\Filter::make('valor')
                    ->form([
                        Forms\Components\TextInput::make('valor_desde')
                            ->numeric()
                            ->label('Valor Desde'),
                        Forms\Components\TextInput::make('valor_hasta')
                            ->numeric()
                            ->label('Valor Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['valor_desde'],
                                fn (Builder $query, $value): Builder => $query->where('valor', '>=', $value),
                            )
                            ->when(
                                $data['valor_hasta'],
                                fn (Builder $query, $value): Builder => $query->where('valor', '<=', $value),
                            );
                    }),
                Tables\Filters\TernaryFilter::make('comicion_borrada')
                    ->label('Seguro Cobrado')
                    ->nullable(),
            ])
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn ($record) => Auth::user()->can('refinanciamientos.edit')),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => Auth::user()->can('refinanciamientos.delete')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ])
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
            'index' => Pages\ListRefinanciamientos::route('/'),
            'create' => Pages\CreateRefinanciamiento::route('/create'),
            'edit' => Pages\EditRefinanciamiento::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (Auth::user()->can('refinanciamientos.view') && !Auth::user()->can('refinanciamientos.index')) {
            $query->whereHas('prestamo', function (Builder $prestamoQuery) {
                $prestamoQuery->where('registrado_id', Auth::id());
            });
        }

        return $query;
    }

    public static function canCreate(): bool
    {
        return Auth::user()->can('refinanciamientos.create');
    }
}