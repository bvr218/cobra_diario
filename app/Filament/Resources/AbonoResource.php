<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbonoResource\Pages;
use App\Models\Abono;
use App\Models\Prestamo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Support\Facades\Request;

class AbonoResource extends Resource
{
    protected static ?string $model = Abono::class;
    protected static ?string $label = 'Abono';
    protected static ?string $pluralLabel = 'Abonos';
    protected static ?string $navigationGroup = 'Gestión de Préstamos';
    protected static ?string $navigationIcon = 'fluentui-money-hand-20-o';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('prestamo_id')
                    ->label('Préstamo')
                    ->options(function () {
                        // Por defecto, aquí no mostramos préstamos eliminados para evitar nuevos abonos a préstamos inactivos.
                        return Prestamo::with('cliente')
                            ->get()
                            ->mapWithKeys(function ($prestamo) {
                                $clienteNombre = $prestamo->cliente->nombre ?? 'Cliente Desconocido';
                                $deudaActual = number_format($prestamo->deuda_actual, 0, ',', '.');
                                $deudaInicial = number_format($prestamo->deuda_inicial, 0, ',', '.');
                                $label = "{$clienteNombre} (ID: {$prestamo->id}, Deuda Actual: \${$deudaActual})";
                                return [$prestamo->id => $label];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->default(fn () => Request::query('record')['prestamo_id'] ?? null)
                    ->required(),
                Placeholder::make('cliente')
                    ->label('Cliente')
                    ->content(function (callable $get) {
                        $prestamoId = $get('prestamo_id');
                        if (!$prestamoId) {
                            return '-- Selecciona primero un Préstamo --';
                        }
                        // Usamos withTrashed por si se está editando un abono de un préstamo eliminado
                        $prestamo = Prestamo::withTrashed()->with('cliente')->find($prestamoId);
                        return $prestamo->cliente->nombre ?? '-- Cliente no encontrado --';
                    }),
                TextInput::make('monto_abono')
                    ->label('Monto del Abono')
                    ->required()
                    ->numeric()
                    ->prefix('COP')
                    ->step(0.01)
                    ->helperText('Use punto (.) unicamente para separar decimales')
                    ->minValue(0),
                DatePicker::make('fecha_abono')
                    ->label('Fecha del Abono')
                    ->required()
                    ->date()
                    ->default(now()),
                TextInput::make('numero_cuota')
                    ->label('Número de Cuota')
                    ->numeric()
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Se asigna automaticamente.'),
                Select::make('registrado_por_id')
                    ->label('Abono Registrado Por')
                    // Aquí también permitimos ver usuarios eliminados si se está editando un registro antiguo
                    ->relationship('registradoPor', 'name', fn(Builder $query) => $query->withTrashed())
                    ->searchable()
                    ->preload()
                    ->disabled(fn () => !request()->user()->hasRole("admin"))
                    ->default(fn () => auth()->user()->id)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 2. COLUMNAS MODIFICADAS PARA MOSTRAR DATOS DE PRÉSTAMOS HUÉRFANOS
                TextColumn::make('prestamo.id')
                    ->label('ID Préstamo')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        // Obtenemos el préstamo incluyendo los borrados para mostrar el ID
                        return $record->prestamo()->withTrashed()->first()?->id ?? 'N/A';
                    }),
                TextColumn::make('prestamo.cliente.nombre')
                    ->label('Cliente')
                    ->sortable()
                    ->searchable()
                    ->getStateUsing(function ($record) {
                        // Obtenemos el préstamo y su cliente, incluyendo borrados
                        return $record->prestamo()->withTrashed()->first()?->cliente?->nombre ?? 'Préstamo o Cliente Eliminado';
                    }),
                TextColumn::make('monto_abono')
                    ->label('Monto Abono')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP')
                    ->searchable()
                    ->summarize(Sum::make()->label('Total')->money('COP', locale: 'es_CO')),
                TextColumn::make('fecha_abono')
                    ->label('Fecha Abono')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('registradoPor.name')
                    ->label('Registrado Por')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->getStateUsing(function ($record) {
                        return $record->registradoPor()->withTrashed()->first()?->name ?? 'Usuario Eliminado';
                    }),
                TextColumn::make('numero_cuota')
                    ->label('N° Cuota')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Fecha Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // TrashedFilter::make(),

                // // 1. NUEVO FILTRO PARA ABONOS DE PRÉSTAMOS ELIMINADOS
                // Filter::make('prestamo_eliminado')
                //     ->label('Préstamo Eliminado')
                //     ->toggle()
                //     ->query(fn (Builder $query): Builder => $query->whereHas('prestamo', function (Builder $prestamoQuery) {
                //         $prestamoQuery->onlyTrashed();
                //     })),

                // Filter::make('usuario_eliminado')
                //     ->label('Usuario Registrado Eliminado')
                //     ->toggle()
                //     ->query(fn (Builder $query): Builder => $query->whereHas('registradoPor', function (Builder $userQuery) {
                //         $userQuery->onlyTrashed();
                //     })),

                Filter::make('fecha_abono_range')
                    ->form([
                        DatePicker::make('fecha_min')->label('Fecha de Abono Desde'),
                        DatePicker::make('fecha_max')->label('Fecha de Abono Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['fecha_min'], fn ($q) => $q->whereDate('fecha_abono', '>=', $data['fecha_min']))
                            ->when($data['fecha_max'], fn ($q) => $q->whereDate('fecha_abono', '<=', $data['fecha_max']));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['fecha_min'] ?? null) $indicators[] = 'Desde: ' . \Carbon\Carbon::parse($data['fecha_min'])->format('d/m/Y');
                        if ($data['fecha_max'] ?? null) $indicators[] = 'Hasta: ' . \Carbon\Carbon::parse($data['fecha_max'])->format('d/m/Y');
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                //     Tables\Actions\RestoreBulkAction::make(),
                //     Tables\Actions\ForceDeleteBulkAction::make(),
                // ]),
            ])
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAbonos::route('/'),
            'create' => Pages\CreateAbono::route('/create'),
            'edit' => Pages\EditAbono::route('/{record}/edit'),
        ];
    }
}