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
use Filament\Tables\Actions\DeleteBulkAction;

class HistorialMovimientoResource extends Resource
{
    protected static ?string $model = HistorialMovimiento::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $navigationLabel = 'Historial de Movimientos';
    protected static ?string $modelLabel = 'Movimiento';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 1. COLUMNA MODIFICADA PARA MOSTRAR USUARIOS BORRADOS
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(function ($record) {
                        // Obtenemos el usuario incluyendo los borrados para mostrar el nombre
                        return $record->user()->withTrashed()->first()?->name ?? 'Usuario Eliminado';
                    }),

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
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                // 2. FILTRO DE SELECCIÓN MODIFICADO PARA INCLUIR USUARIOS BORRADOS
                SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'name', fn (Builder $query) => $query->withTrashed())
                    ->preload()
                    ->searchable(),
                
                // 3. NUEVO FILTRO PARA VER REGISTROS HUÉRFANOS
                Filter::make('usuario_eliminado')
                    ->label('Usuario Eliminado')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereHas('user', function (Builder $userQuery) {
                        $userQuery->onlyTrashed();
                    })),

                SelectFilter::make('tipo')
                    ->options([
                        'creación'           => 'Creación',
                        'edición'            => 'Edición',
                        'eliminación'        => 'Eliminación',
                        'ajuste_dinero_base' => 'Ajuste',
                    ]),

                SelectFilter::make('es_edicion')
                    ->label('¿Es Edición?')
                    ->options([
                        1 => 'Sí',
                        0 => 'No',
                    ]),

                Filter::make('fecha_range')
                    ->label('Rango de Fechas')
                    ->form([
                        DatePicker::make('fecha_desde')->label('Desde'),
                        DatePicker::make('fecha_hasta')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['fecha_desde'], fn (Builder $q) => $q->whereDate('fecha', '>=', $data['fecha_desde']))
                            ->when($data['fecha_hasta'], fn (Builder $q) => $q->whereDate('fecha', '<=', $data['fecha_hasta']));
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
                        if (is_string($datos)) $datos = json_decode($datos, true);
                        if (!is_array($datos)) $datos = [];
                        return view('components.historial.json-modal', ['titulo' => 'Estado anterior', 'datos' => $datos]);
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
                        if (is_string($datos)) $datos = json_decode($datos, true);
                        if (!is_array($datos)) $datos = [];
                        return view('components.historial.json-modal', ['titulo' => 'Estado actualizado', 'datos' => $datos]);
                    }),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     DeleteBulkAction::make(),
                // ])->label('Acciones en masa'),
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