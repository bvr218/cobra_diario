<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AbonoResource\Pages;
use App\Filament\Resources\AbonoResource\RelationManagers;
use App\Models\Abono;
use App\Models\Prestamo;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Filters\Filter;

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
                        return Prestamo::with('cliente')
                            ->get()
                            ->mapWithKeys(function ($prestamo) {
                                $clienteNombre = $prestamo->cliente->nombre ?? 'Cliente Desconocido';
                                $deudaActual = number_format($prestamo->deuda_actual, 0, ',', '.');
                                $deudaInicial = number_format($prestamo->deuda_inicial, 0, ',', '.');
                                // Formato similar al de RefinanciamientoResource
                                $label = "{$clienteNombre} (Deuda Inicial: \${$deudaInicial}, Deuda Actual: \${$deudaActual})";
                                return [$prestamo->id => $label];
                            });
                    })
                    ->searchable()
                    ->preload()
                    ->reactive()
                    ->default( fn () => Request::query('record')['prestamo_id'] ?? null)
                    ->required(),
                Placeholder::make('cliente')
                    ->label('Cliente')
                    ->content(function (callable $get) {
                        $prestamoId = $get('prestamo_id');
                        if (! $prestamoId) {
                            return '-- Selecciona primero un Préstamo --';
                        }
                        $prestamo = Prestamo::with('cliente')->find($prestamoId);
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
                    ->relationship('registradoPor', 'name')
                    ->searchable()
                    ->preload()
                    ->disabled(fn()=>!request()->user()->hasRole("admin"))
                    ->default(fn () => auth()->user()->id)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('prestamo.id')
                    ->label('ID del Préstamo')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('prestamo.cliente.nombre')
                    ->label('Cliente')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('monto_abono')
                    ->label('Monto del Abono')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP')
                    ->searchable(),
                TextColumn::make('fecha_abono')
                    ->label('Fecha del Abono')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('monto_abono')
                    ->label('Monto del Abono')
                    ->sortable()
                    ->formatStateUsing(fn ($state) => '$' . number_format($state, 0, ',', '.') . ' COP')
                    ->searchable()
                    ->summarize(Sum::make()
                        ->label('Total')
                        ->money('COP', locale: 'es_CO')
                    ),

                TextColumn::make('registradoPor.name')
                    ->label('Abono Registrado Por')
                    ->sortable()
                    ->toggleable()
                    ->default(false)
                    ->searchable(),
                TextColumn::make('numero_cuota')
                    ->label('Número de Cuota')
                    ->sortable()
                    ->searchable() 
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                    
            ])->filters([
                //
            ])->headerActions([
                //
            ])->actions([
                //
            ])->bulkActions([
                //
            ])
            ->filters([
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
            
                        if ($data['fecha_min'] ?? null) {
                            $indicators[] = 'Desde: ' . \Carbon\Carbon::parse($data['fecha_min'])->format('d/m/Y');
                        }
            
                        if ($data['fecha_max'] ?? null) {
                            $indicators[] = 'Hasta: ' . \Carbon\Carbon::parse($data['fecha_max'])->format('d/m/Y');
                        }
            
                        return $indicators;
                    }),
            ])
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Tables\Actions\DeleteBulkAction::make(),
                ]),
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
            'index' => Pages\ListAbonos::route('/'),
            'create' => Pages\CreateAbono::route('/create'),
            'edit' => Pages\EditAbono::route('/{record}/edit'),
        ];
    }
}
