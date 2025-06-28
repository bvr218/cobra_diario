<?php

namespace App\Filament\Resources;

use App\Models\User;
use Filament\Tables;
use App\Models\HistorialMovimiento;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Resources\HistorialMovimientoResource\Pages;
// Asegúrate de que esta importación esté presente
use Filament\Tables\Actions\DeleteBulkAction;


class HistorialMovimientoResource extends Resource
{

    public static function canAccess(): bool
    {
        return false;
    }


    protected static ?string $model = HistorialMovimiento::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Registros';
    protected static ?string $navigationLabel = 'Historial de Movimientos';
    protected static ?string $modelLabel = 'Movimiento';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tabla_origen')
                    ->label('Origen')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('descripcion')
                    ->label('Descripción')
                    ->searchable()
                    ->limit(50)
                    ->wrap()
                    ->formatStateUsing(fn ($state) => $state === 'comisión' ? 'Seguro' : $state),

                Tables\Columns\TextColumn::make('monto')
                    ->label('Monto')
                    ->money('COP', locale: 'es_CO')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('es_edicion')
                    ->label('¿Es Edición?')
                    ->formatStateUsing(fn ($state) => $state ? 'SI' : 'NO')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha de Movimiento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'name')
                    ->preload()
                    ->searchable(),

                SelectFilter::make('tipo')
                    ->options([
                        'creación'          => 'Creación',
                        'edición'           => 'Edición',
                        'eliminación'       => 'Eliminación',
                        'ajuste_dinero_base' => 'Ajuste',
                    ]),

                SelectFilter::make('es_edicion')
                    ->label('¿Es Edición?')
                    ->options([
                        1 => 'Sí',
                        0 => 'No',
                    ]),

                Filter::make('fecha_desde')
                    ->label('Fecha desde')
                    ->form([
                        DatePicker::make('fecha_desde')->label('Desde'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['fecha_desde']) {
                            $query->whereDate('fecha', '>=', $data['fecha_desde']);
                        }
                    }),

                Filter::make('fecha_hasta')
                    ->label('Fecha hasta')
                    ->form([
                        DatePicker::make('fecha_hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        if ($data['fecha_hasta']) {
                            $query->whereDate('fecha', '<=', $data['fecha_hasta']);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // Ver anterior: ahora visible si es edición o si es creación
                Tables\Actions\Action::make('ver_anterior')
                    ->label('Ver anterior')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Estado Anterior')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->color('gray')
                    ->visible(fn ($record) => $record->es_edicion === true || $record->tipo === 'creación')
                    ->action(fn () => null)
                    ->modalContent(function ($record) {
                        $datos = $record->cambio_desde ?? [];

                        if (is_string($datos)) {
                            $datos = json_decode($datos, true);
                        }
                        if (!is_array($datos)) {
                            $datos = [];
                        }

                        return view('components.historial.json-modal', [
                            'titulo' => 'Estado anterior',
                            'datos'   => $datos,
                        ]);
                    }),

                Tables\Actions\Action::make('ver_actualizado')
                    ->label('Ver actualizado')
                    ->icon('heroicon-o-document-text')
                    ->modalHeading('Estado Actualizado')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->color('primary')
                    ->visible(fn ($record) => $record->es_edicion === true)
                    ->action(fn () => null)
                    ->modalContent(function ($record) {
                        $datos = $record->cambio_hacia ?? [];

                        if (is_string($datos)) {
                            $datos = json_decode($datos, true);
                        }
                        if (!is_array($datos)) {
                            $datos = [];
                        }

                        return view('components.historial.json-modal', [
                            'titulo' => 'Estado actualizado',
                            'datos'   => $datos,
                        ]);
                    }),
            ])
            // AÑADIENDO LA ACCIÓN EN MASA PARA BORRAR
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ])->label('Acciones en masa'), // Opcional: etiqueta para el grupo de acciones
            ])
            ->defaultSort('fecha', 'desc')
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHistorialMovimientos::route('/'),
            'edit'  => Pages\EditHistorialMovimiento::route('/{record}/edit'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}