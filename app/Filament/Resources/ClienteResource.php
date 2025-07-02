<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Actions\Action as TablesAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;
    protected static ?string $label = 'Cliente';
    protected static ?string $pluralLabel = 'Clientes';
    protected static ?string $navigationGroup = 'Administración de Usuarios';
    protected static ?string $navigationIcon = 'heroicon-o-users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->label('Nombre completo')
                    ->required()
                    ->maxLength(200),

                Forms\Components\TextInput::make('numero_cedula')
                    ->label('Número de Cédula')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(20),

                Forms\Components\Repeater::make('telefonos')
                    ->simple(
                        Forms\Components\TextInput::make('telefono')
                            ->label('Teléfono')
                            ->required()
                    )
                    ->label('Teléfonos'),

                Forms\Components\TextInput::make('ciudad')
                    ->label('Ciudad')
                    ->required()
                    ->maxLength(50),

                Forms\Components\TextInput::make('direccion')
                    ->label('Dirección')
                    ->required()
                    ->maxLength(100),

                // Forms\Components\TextInput::make('coordenadas')
                //     ->label('Coordenadas')
                //     ->required()
                //     ->maxLength(100)
                //     ->helperText('Puedes seleccionar la ubicación en el mapa')
                //     ->suffixAction(
                //         Forms\Components\Actions\Action::make("ubicar")
                //             ->icon("heroicon-c-map-pin")
                //             ->modalHeading('Ubicar en Mapa')
                //             ->modalWidth('3xl')
                //             ->modalSubmitActionLabel('Guardar Coordenadas')
                //             ->fillForm(function (Forms\Get $get, array $data): array {
                //                 return [
                //                     'coordenada' => $get('coordenadas'),
                //                 ];
                //             })
                //             ->form(function (Forms\Get $get) {
                //                 $modalInputId = 'modal_coordenada_input';
                //                 return [
                //                     Forms\Components\TextInput::make('coordenada')
                //                         ->label('Coordenadas Seleccionadas')
                //                         ->id($modalInputId)
                //                         ->required()
                //                         ->readOnly()
                //                         ->helperText('Haz clic en el mapa para seleccionar una ubicación.')
                //                         ->maxLength(100),

                //                     Forms\Components\Livewire::make('mapView', [
                //                         'coordinatesInputId' => $modalInputId,
                //                         'userList'          => json_encode([]),
                //                         'initialCoordinates'=> $get('coordenadas'),
                //                     ])
                //                         ->key('map-modal-component'),
                //                 ];
                //             })
                //             ->action(function (array $data, $set) {
                //                 $newCoordinates = $data['coordenada'];
                //                 $set('coordenadas', $newCoordinates);
                //             })
                //     ),

                Forms\Components\TextInput::make('recomendado')
                    ->label('Recomendado por')
                    ->nullable()
                    ->maxLength(30),

                Forms\Components\Select::make('registrado_por')
                    ->relationship('registradoPor', 'name')
                    ->label('Cliente asignado a')
                    ->preload()
                    ->nullable()
                    ->searchable()
                    ->placeholder('Selecciona un usuario')
                    ->default(fn() => auth()->id())
                    ->visible(fn() => (
                        Auth::user()->can('clientes.index')
                        || Auth::user()->can('clientesOficina.index')
                    ))
                    ->disabled(fn() => (
                        (Auth::user()->can('clientes.create') && Auth::user()->can('clientes.view'))
                        || Auth::user()->can('clientesOficina.index')
                    )),

                Forms\Components\Textarea::make('descripcion')
                    ->label('Descripción')
                    ->nullable()
                    ->maxLength(255),

                Forms\Components\Select::make('oficina_id')
                    ->label('Oficina')
                    ->options(fn() => User::role('oficina')->pluck('name', 'id')->toArray())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->visible(fn() => (
                        Auth::user()->can('clientes.index')
                        || Auth::user()->can('clientesOficina.index')
                    ))
                    ->default(fn(?Cliente $record) => match (true) {
                        auth()->user()->can('clientes.index')          => $record?->oficina_id,
                        auth()->user()->can('clientesOficina.index')   => auth()->id(),
                        auth()->user()->can('clientes.view')           => auth()->user()->oficina_id,
                        default                                         => null,
                    })
                    ->disabled(fn() => !auth()->user()->can('clientes.index'))
                    ->placeholder('Selecciona una oficina')
                    ->helperText(fn() => auth()->user()->can('clientesOficina.index')
                        ? 'Recuerda seleccionar la oficina correspondiente.'
                        : null
                    ),

                Forms\Components\FileUpload::make('foto_cliente')
                    ->label('Foto del Cliente')
                    ->image()
                    ->disk('public')
                    ->directory('clientes')
                    ->preserveFilenames()
                    ->enableOpen()
                    ->nullable()
                    ->enableDownload(),

                Forms\Components\FileUpload::make('galeria')
                    ->label('Galería')
                    ->image()
                    ->disk('public')
                    ->directory('clientes')
                    ->preserveFilenames()
                    ->enableOpen()
                    ->panelLayout('grid')
                    ->nullable()
                    ->multiple()
                    ->enableDownload(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nombre')
                    ->label('Ficha del cliente')
                    ->sortable()
                    ->searchable()
                    ->html()
                    ->formatStateUsing(function ($record) {
                        $imagen = $record->foto_cliente
                            ? asset('storage/' . $record->foto_cliente)
                            : url('/storage/avatar-default.jpeg');

                        $html = '
                            <div class="flex items-center space-x-4">
                                <div class="w-16 h-16 rounded-lg overflow-hidden">
                                    <img src="' . $imagen . '" alt="Foto del Cliente" class="w-full h-full object-cover">
                                </div>
                                <div class="flex-1">
                                    <strong>' . e($record->nombre) . '</strong><br>
                                    <strong>CC:</strong> ' . e($record->numero_cedula) . '<br>
                                    <strong>Dirección:</strong> ' . e($record->direccion . ' ( ' . $record->ciudad . ' )') . '<br>
                                    <strong>Celular:</strong> ' . e($record->telefonos[0] ?? '') . '<br>
                                    <strong>Oficina:</strong> ' . e($record->oficina->name ?? '') . '<br>
                                    <strong>Asignado a:</strong> ' . e($record->registradoPor->name ?? '') . '<br>
                                    <strong>Descripción:</strong> ' . e(Str::limit($record->descripcion ?? '', 40)) . '
                                </div>
                            </div>
                        ';
                        return new HtmlString($html);
                    }),

                ViewColumn::make('reputacion_display')
                    ->label('Calificación')
                    ->view('filament.tables.columns.reputation-stars'),

                TextColumn::make('id')
                    ->label('Información de Préstamos')
                    ->html()
                    ->sortable()
                    ->formatStateUsing(function ($record) {
                        $prestamos = $record->prestamos;

                        if ($prestamos->isEmpty()) {
                            return new HtmlString('
                                <div class="flex items-center">
                                    <div class="flex flex-col">
                                        <span class="text-gray-600 font-bold">No tiene Préstamos</span>
                                    </div>
                                </div>
                            ');
                        }

                        $cantidad = $prestamos->count();
                        $deudaTotal = $prestamos->sum('deuda_actual');

                        // Buscar el pago futuro más cercano
                        $pagoMasCercano = $prestamos
                            ->filter(fn($p) => Carbon::parse($p->next_payment)->gte(now()))
                            ->sortBy('next_payment')
                            ->first();

                        if (! $pagoMasCercano) {
                            $pagoMasCercano = $prestamos
                                ->filter(fn($p) => Carbon::parse($p->next_payment)->lt(now()))
                                ->sortByDesc('next_payment')
                                ->first();
                        }

                        if ($pagoMasCercano) {
                            $fechaPago = Carbon::parse($pagoMasCercano->next_payment);
                            $esFuturo = $fechaPago->gte(now());
                            $color = $esFuturo ? 'text-green-600' : 'text-red-600';
                            $estado = $esFuturo ? 'Próximo pago:' : 'Vencido desde:';

                            $textoFecha = '<span class="' . $color . ' font-bold">' . $fechaPago->format('d-m-Y') . '</span>';
                        } else {
                            $estado = 'Sin pagos registrados';
                            $textoFecha = '<em>No hay pagos</em>';
                        }

                        return new HtmlString('
                            <div class="flex items-center">
                                <div class="flex-1 space-y-1">
                                    <strong>Total de Préstamos:</strong> ' . $cantidad . '<br>
                                    <strong>Deuda Total:</strong> $' . number_format($deudaTotal, 0, ',', '.') . ' COP<br>
                                    <strong>' . $estado . '</strong> ' . $textoFecha . '<br>
                                    <b>Pagos atrasados actuales:</b> <span class="text-red-600">' . $record->pagos_atrasados . '</span>
                                </div>
                            </div>
                        ');
                    }),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Última Actualización')
                    ->dateTime('d-m-Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('cedula_o_celular')
                    ->form([
                        Forms\Components\TextInput::make('busqueda')
                            ->label('Buscar por Cédula o Celular')
                            ->placeholder('Ingrese cédula o celular...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['busqueda'],
                            function (Builder $query, $search) {
                                $query->where(function (Builder $query) use ($search) {
                                    $query->where('numero_cedula', 'like', "%{$search}%")
                                        ->orWhere('telefonos', 'like', "%{$search}%");
                                });
                            }
                        );
                    }),
                Tables\Filters\SelectFilter::make('con_o_sin_prestamos')
                    ->label('Filtro de Préstamos')
                    ->options([
                        'con' => 'Con Préstamos',
                        'sin' => 'Sin Préstamos',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'con') {
                            return $query->whereHas('prestamos');
                        }
                        if ($data['value'] === 'sin') {
                            return $query->whereDoesntHave('prestamos');
                        }
                        return $query;
                    })
                    ->visible(fn() => !auth()->user()->can('clientes.view')),

                Tables\Filters\SelectFilter::make('oficina_id')
                    ->label('Oficina')
                    ->options(User::role('oficina')->pluck('name', 'id'))
                    ->query(fn(Builder $query, array $data): Builder =>
                        filled($data['value'])
                            ? $query->where('oficina_id', $data['value'])
                            : $query
                    )
                    ->visible(fn() => auth()->user()->can('clientes.index')),

                Tables\Filters\SelectFilter::make('registrado_por')
                    ->label('Asignado a')
                    ->options(function () {
                        // Asegúrate de importar el modelo User: use App\Models\User;
                        return \App\Models\User::pluck('name', 'id');
                    })
                    ->query(fn(Builder $query, array $data): Builder =>
                        filled($data['value'])
                            ? $query->where('registrado_por', $data['value'])
                            : $query
                    )
                    ->searchable()
                    ->preload()
                    ->visible(fn() =>
                        auth()->user()->can('clientes.index') ||
                        auth()->user()->can('clientesOficina.index')
                    ),

                Tables\Filters\SelectFilter::make('reputacion')
                    ->label('Reputación')
                    ->placeholder('Todas las reputaciones')
                    ->options([
                        5 => '⭐⭐⭐⭐⭐ (Excelente)',
                        4 => '⭐⭐⭐⭐ (Buena)',
                        3 => '⭐⭐⭐ (Neutral)',
                        2 => '⭐⭐ (Mala)',
                        1 => '⭐ (Pésima)',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'])) {
                            return $query;
                        }
                        $value = (int) $data['value'];
                        return $query->whereRaw('
                            (
                                SELECT ROUND(AVG(
                                    CASE
                                        WHEN abonos.monto_pagado > 0 THEN 
                                            (CASE WHEN abonos.monto_abono >= abonos.monto_pagado THEN 1 ELSE -1 END)
                                            +
                                            (CASE WHEN abonos.fecha_abono <= abonos.fecha_pagado THEN 1 ELSE -1 END)
                                        ELSE 0
                                    END
                                ) + 3)
                                FROM prestamos
                                LEFT JOIN abonos ON prestamos.id = abonos.prestamo_id
                                WHERE prestamos.cliente_id = clientes.id
                            ) = ?
                        ', [$value]);
                    }),
            ])            
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(fn() => auth()->user()->can('clientes.view') ? 'Ver Cliente' : 'Editar')
                    ->icon(fn() => auth()->user()->can('clientes.view') ? null : 'heroicon-m-pencil-square')
                    ->visible(fn() => auth()->user()->can('clientes.view') || auth()->user()->can('clientes.edit')),

                TablesAction::make('verHistorial')
                    ->label('Ver Historial')
                    ->icon('heroicon-o-document-magnifying-glass')
                    ->color('info')
                    ->visible(fn(): bool => auth()->user()->can('clientes.index'))
                    ->modalHeading(fn(Cliente $record): string => 'Historial de ' . $record->nombre)
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn(Cliente $record) => view(
                        'filament.forms.components.cliente-historial-viewer',
                        ['cliente' => $record]
                    )),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
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
            'index'  => Pages\ListClientes::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'edit'   => Pages\EditCliente::route('/{record}/edit'),
        ];
    }
}
