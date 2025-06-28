<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrestamoResource\Pages;
use App\Filament\Resources\PrestamoResource\RelationManagers\AbonosRelationManager;
use App\Models\Prestamo;
use App\Models\Refinanciamiento;
use App\Models\User;
use App\Models\Cliente;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Forms\Components\Actions;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\BadgeColumn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class PrestamoResource extends Resource
{
    protected static ?string $model = Prestamo::class;

    protected static ?string $label = 'Préstamo';

    protected static ?string $pluralLabel = 'Préstamos';

    protected static ?string $navigationGroup = 'Gestión de Préstamos';

    protected static ?string $navigationIcon = 'fluentui-wallet-credit-card-16-o';

    public static function form(Form $form): Form
    {
        $user = auth()->user();

        return $form
            ->schema([
                Placeholder::make('deuda_actual')
                    ->label('Deuda Actual')
                    ->content(fn (?Prestamo $record) =>
                        $record ? '$' . number_format($record->deuda_actual, 0) . ' COP' : 'N/A'
                    ),

                Placeholder::make('monto_por_cuota')
                    ->label('Valor de Cuota Diaria')
                    ->content(fn (?Prestamo $record) =>
                        $record ? '$' . number_format($record->monto_por_cuota, 0) . ' COP' : 'N/A'
                    ),

                Select::make('cliente_id')
                    ->label('Cliente')
                    ->required()
                    ->helperText(function () {
                        if (Auth::user()->can('prestamos.view')) {
                            return 'Solamente aparecen Clientes de su Oficina';
                        }
                        return null; // No mostrar helpertext si no tiene el permiso
                    })
                    ->relationship('cliente', 'nombre')
                    ->options(function () {
                        $user = auth()->user();

                        if ($user->can('prestamos.index')) {
                            return Cliente::pluck('nombre', 'id');
                        }

                        if ($user->can('prestamosOficina.index')) {
                            return Cliente::where('oficina_id', $user->id)
                                ->pluck('nombre', 'id');
                        }

                        if ($user->can('prestamos.view')) {
                            // Si tiene permiso 'prestamos.view', muestra solo los clientes donde el usuario es el registrado_id
                            return Cliente::where('registrado_por', $user->id)
                                ->pluck('nombre', 'id');
                        }

                        return [];
                    })
                    ->searchable()
                    ->preload()
                    ->live() // Hace que el campo sea "reactivo" para que afterStateUpdated se dispare
                    ->afterStateUpdated(function ($state, callable $set, callable $get) {
                        if ($state) {
                            $cliente = Cliente::find($state);
                            if ($cliente && $cliente->registrado_por) {
                                // Pre-llenar 'registrado_id' si el campo es visible para el usuario actual
                                if (auth()->user()->can('prestamos.index') || auth()->user()->can('prestamosOficina.index')) {
                                    $set('registrado_id', $cliente->registrado_por);
                                }
                                // Pre-llenar 'agente_asignado' si el campo es visible y el usuario actual NO es un agente
                                // (para no sobrescribir la auto-asignación de agentes)
                                if ((auth()->user()->can('prestamos.index') || auth()->user()->can('prestamosOficina.index')) && !auth()->user()->hasRole('agente')) {
                                    $set('agente_asignado', $cliente->registrado_por);
                                }
                            }
                        }
                    }),

                Select::make('frecuencia_id')
                    ->label('Planes de Pago')
                    ->required()
                    ->relationship('frecuencia', 'name')
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')->required(),
                        Forms\Components\TextInput::make('dias')
                            ->required()
                            ->label('Frecuencia')
                            ->suffix('Días')
                            ->integer(),
                    ])
                    ->searchable()
                    ->preload(),

                Select::make('estado')
                    ->required()
                    ->label('Estado')
                    ->options([
                        'pendiente'  => 'Pendiente',
                        'autorizado' => 'Autorizado',
                        'negado'     => 'Negado',
                        'activo'     => 'Activo',
                        'finalizado' => 'Finalizado',
                    ])
                    ->disabled(fn () => ! $user->can('prestamos.index'))
                    ->visible(fn () => $user->can('prestamos.index'))
                    ->default('pendiente'),

                DatePicker::make('initial_date')
                    ->required()
                    ->default(now()->addDay()->toDateString())
                    ->label('Fecha de primer pago'),

                TextInput::make('valor_total_prestamo')
                    ->label('Valor del Préstamo')
                    ->numeric()
                    ->required()
                    ->prefix('COP')
                    ->minValue(0),

                TextInput::make('interes')
                    ->label('Tasa de Interés (%)')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%'),

                TextInput::make('numero_cuotas')
                    ->label('Número de Cuotas')
                    ->numeric()
                    ->required()
                    ->minValue(1),

                TextInput::make('comicion')
                    ->label('Valor de Seguro')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->helperText('Si este seguro ya fue liquidado, no podrá ser editado')
                    ->disabled(fn ($get) => $get('comicion_borrada')) // Deshabilita si comicion_borrada es true
                    ->dehydrated(fn ($get) => !$get('comicion_borrada')), // No guarda si comicion_borrada es true

                Select::make('agente_asignado')
                    ->label('Agente Asignado')
                    ->nullable()
                    ->options(function () {
                        $user = Auth::user();

                        if ($user->can('prestamos.index')) {
                            return User::role('agente')->pluck('name', 'id');
                        }

                        if ($user->can('prestamosOficina.index')) {
                            return User::role('agente')
                                ->where('oficina_id', $user->id)
                                ->pluck('name', 'id');
                        }

                        if ($user->can('prestamos.view')) {
                            return User::role('agente')
                                ->where('oficina_id', $user->oficina_id)
                                ->pluck('name', 'id');
                        }

                        return [];
                    })
                    ->default(fn () => Auth::user()->hasRole('agente') ? Auth::id() : null)
                    ->disabled(fn () => Auth::user()->hasRole('agente'))
                    ->searchable()
                    ->visible(fn () =>
                        Auth::user()->can('prestamos.index') ||
                        Auth::user()->can('prestamosOficina.index')
                    )
                    ->preload() // Mantenemos preload
                    ->required(),

                Select::make('registrado_id')
                    ->label('Préstamo Registrado Por')
                    ->relationship('registrado', 'name')
                    ->searchable()
                    ->preload()
                    ->options(User::pluck('name', 'id')) // Mantenemos las opciones
                    ->visible(fn () => auth()->user()->can('prestamos.index') || auth()->user()->can('prestamosOficina.index'))
                    // El valor por defecto se maneja en las páginas Create/Edit para asegurar que se asigne correctamente basado en el cliente seleccionado.
                    ->required(fn () => auth()->user()->can('prestamos.index') || auth()->user()->can('prestamosOficina.index'))
                    ->default(auth()->id()), // Mantenemos el default para la carga inicial

                Hidden::make('comicion_borrada')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user  = auth()->user();
        $today = Carbon::now()->startOfDay();

        return $table
            ->columns([
                TextColumn::make('cliente.nombre')
                    ->label('Detalle del Préstamo')
                    ->html()
                    ->searchable()
                    ->formatStateUsing(function (Prestamo $record) use ($today) {
                        $c     = $record->cliente;
                        $f     = $record->frecuencia;
                        $reg   = $record->registrado;
                        $img   = $c->foto_cliente
                            ? asset('storage/' . $c->foto_cliente)
                            : '/storage/avatar-default.jpg';

                        $deuda             = $record->deuda_actual;
                        $estadoPrestamo    = '';
                        $colorEstado       = '';
                        $valorRefinanc = $record->refinanciamientos()->where('estado', 'autorizado')->sum('valor') ?? 0;

                        if ($deuda <= 0) {
                            $estadoPrestamo = 'Préstamo Finalizado';
                            $colorEstado    = 'red';
                        } elseif ($deuda <= ($record->valor_total_prestamo * 0.3)) {
                            $estadoPrestamo = 'El Préstamo está por Terminar';
                            $colorEstado    = 'orange';
                        }

                        // Mensaje condicional
                        $infoFinal = '';

                        if ($deuda <= 0) {
                            $infoFinal = '<p style="margin:0;"><strong>Estado préstamo:</strong> '
                                . '<span style="color:' . $colorEstado . '; font-weight:600;">'
                                . $estadoPrestamo
                                . '</span></p>';
                        } else {
                            $next       = Carbon::parse($record->next_payment);
                            $colorFecha = $next->isSameDay($today) || $next->isBefore($today)
                                ? 'red'
                                : 'green';
                            $fecha      = $next->format('d/m/Y');

                            $infoFinal = '<p style="margin:0;"><strong>Próximo pago:</strong> '
                                . '<span style="color:' . $colorFecha . '; font-weight:600;">'
                                . $fecha
                                . '</span></p>';

                            if ($estadoPrestamo !== '') {
                                $infoFinal .= '<p style="margin:0;"><strong>Estado préstamo:</strong> '
                                    . '<span style="color:' . $colorEstado . '; font-weight:600;">'
                                    . $estadoPrestamo
                                    . '</span></p>';
                            }
                        }

                        $descripcion = $c->descripcion ?? '';
                        $descripcion = \Illuminate\Support\Str::limit(strip_tags($descripcion), 40);

                        $html = '
                            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:1rem;">
                                <div style="flex-shrink:0; width:60px; height:60px; border-radius:0.5rem; overflow:hidden;">
                                    <img src="' . $img . '" alt="Foto Cliente" style="width:100%; height:100%; object-fit:cover;" />
                                </div>
                                <div class="texto-filament" style="flex:1; min-width:200px; font-size:0.875rem;">
                                    <p style="margin:0; font-weight:600;" class="texto-strong">' . e($c->nombre) . '</p>
                                    <p style="margin:0;"><strong>Descripción:</strong> ' . e($descripcion) . '</p>
                                    <p style="margin:0;"><strong>Préstamo registrado por:</strong> ' . e($reg?->name) . '</p>
                                    <p style="margin:0;"><strong>Valor préstamo:</strong> $' . number_format($record->valor_total_prestamo, 0, ',', '.') . ' COP</p>
                                    <p style="margin:0;"><strong>Valor refinanciado:</strong> $' . number_format($valorRefinanc, 0, ',', '.') . ' COP</p>
                                    <p style="margin:0;"><strong>Deuda Inicial:</strong> $' . number_format($record->deuda_inicial, 0, ',', '.') . ' COP</p>
                                    <p style="margin:0;"><strong>Deuda actual:</strong> $' . number_format($deuda, 0, ',', '.') . ' COP</p>
                                    <p style="margin:0;"><strong>Cuota:</strong> $' . number_format($record->monto_por_cuota, 0, ',', '.') . ' COP</p>
                                    <p style="margin:0;"><strong>Interés:</strong> ' . e($record->interes) . '%</p>
                                    <p style="margin:0;"><strong>Frecuencia:</strong> ' . e($f->name) . '</p>
                                    <p style="margin:0;"><strong>Cuotas:</strong> ' . e($record->numero_cuotas) . '</p>
                                    <p style="margin:0;"><strong>Seguro:</strong> $' . number_format($record->comicion, 0, ',', '.') . ' COP</p>
                                    ' . $infoFinal . '
                                </div>
                            </div>

                            <style>
                                @media (prefers-color-scheme: dark) {
                                    .texto-filament {
                                        color: #d1d5db !important;
                                    }
                                    .texto-filament .texto-strong {
                                        color: #ffffff !important;
                                    }
                                }
                            </style>
                        ';

                        return new HtmlString($html);
                    }),

                SelectColumn::make('agente_asignado')
                    ->label('Agente Asignado')
                    ->sortable()
                    ->options(function () {
                        $user = Auth::user();
                        if ($user->hasRole('admin')) {
                            return User::role('agente')->pluck('name', 'id')->toArray();
                        }
                        if ($user->hasRole('oficina')) {
                            return User::role('agente')
                                ->where('oficina_id', $user->id)
                                ->pluck('name', 'id')
                                ->toArray();
                        }
                        if ($user->hasRole('agente')) {
                            return User::role('agente')
                                ->where('oficina_id', $user->oficina_id)
                                ->pluck('name', 'id')
                                ->toArray();
                        }
                        return User::role('agente')->pluck('name', 'id')->toArray();
                    })
                    ->placeholder('Sin Agente Asignado')
                    ->searchable()
                    ->disabled(fn (Prestamo $record) =>
                        $record->estado === 'finalizado' ||
                        !(
                            Auth::user()->can('prestamos.edit') ||
                            (!Auth::user()->can('prestamos.edit') && Auth::user()->can('activarPrestamosOficina.view'))
                        )
                    ),

                SelectColumn::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente'  => 'Pendiente',
                        'autorizado' => 'Autorizado',
                        'negado'     => 'Negado',
                        'activo'     => 'Activo',
                        'finalizado' => 'Finalizado',
                    ])
                    ->default('pendiente')
                    ->searchable()
                    ->disabled(fn () => ! $user->can('prestamos.index')),

                // Columna extra para mostrar el mensaje cuando existe refinanciación pendiente
                TextColumn::make('refinancing_message') // Usamos el nombre del accesor
                    ->label('') // Dejamos la etiqueta vacía, el mensaje es el contenido
                    ->html()

                    ->searchable(query: function ($query, string $search) {
                        if (str_contains('Refinanciación pendiente', $search) && !Auth::user()->can('prestamos.index')) {
                            $query->orWhereHas('refinanciamientos', function ($q) {
                                $q->where('estado', 'pendiente');
                            });
                        }
                        if (str_contains('No refinanciable', $search) && !Auth::user()->can('prestamos.index')) {
                            $query->orWhereHas('refinanciamientos', function ($q) {
                                $q->where('estado', 'negado');
                            });
                        }
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('cedula_o_celular_cliente')
                    ->form([
                        Forms\Components\TextInput::make('busqueda')
                            ->label('Buscar por Cédula o Celular del Cliente')
                            ->placeholder('Ingrese cédula o celular...'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['busqueda'],
                            function (Builder $query, $search) {
                                // Usamos whereHas para filtrar basado en la relación 'cliente'
                                $query->whereHas('cliente', function (Builder $clienteQuery) use ($search) {
                                    $clienteQuery->where(function (Builder $subQuery) use ($search) {
                                        $subQuery->where('numero_cedula', 'like', "%{$search}%")
                                                 ->orWhere('telefonos', 'like', "%{$search}%");
                                    });
                                });
                            }
                        );
                    }),
                SelectFilter::make('estado')
                    ->label('Estado')
                    ->options([
                        'pendiente'  => 'Pendiente',
                        'autorizado' => 'Autorizado',
                        'negado'     => 'Negado',
                        'activo'     => 'Activo',
                        'finalizado' => 'Finalizado',
                    ]),

                SelectFilter::make('agente_asignado')
                    ->label('Agente Asignado')
                    ->options(function () {
                        if (Auth::user()->can('prestamosOficina.index')) {
                            return User::role('agente')
                                ->where('oficina_id', Auth::user()->id)
                                ->pluck('name', 'id')
                                ->toArray();
                        } elseif (Auth::user()->can('prestamos.index')) {
                            return User::role('agente')->pluck('name', 'id')->toArray();
                        } elseif (Auth::user()->can('prestamos.view')) {
                            return User::where('id', Auth::id())->pluck('name', 'id')->toArray();
                        }
                        return [];
                    })
                    ->placeholder('Todos los agentes')
                    ->visible(fn () =>
                        !Auth::user()->can('prestamos.view') ||
                        (
                            Auth::user()->can('asignarAgentePrestamo.view') &&
                            Auth::user()->can('prestamos.view')
                        ) ||
                        Auth::user()->can('asignarAgentePrestamo.view')
                    ),
                SelectFilter::make('estado_pago')
                    ->label('Estado de Pago')
                    ->options([
                        'vencido' => 'Préstamos Vencidos',
                        'al_dia' => 'Préstamos al Día',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $today = Carbon::today()->toDateString();
                        $value = $data['value'] ?? null;

                        if (empty($value)) {
                            return $query;
                        }

                        if ($value === 'vencido') {
                            return $query->whereIn('estado', ['activo', 'autorizado']) // Solo préstamos activos o autorizados pueden estar vencidos
                                ->where(function (Builder $q) use ($today) {
                                    // Sub-bloque: Casos donde hay abonos (según lógica del accesor para promesas)
                                    $q->orWhere(function(Builder $qConAbonos) use ($today) {
                                        $qConAbonos->whereHas('abonos')
                                            ->where(function(Builder $qPromesaOAbono) use ($today) {
                                                // Caso 1.1: Hay abonos, hay promesa relevante, y promesa.to_pay = $today (vencido hoy)
                                                $qPromesaOAbono->orWhere(function (Builder $qPromesa) use ($today) {
                                                    $qPromesa->whereHas('promesas', function (Builder $p) use ($today) {
                                                        $p->where('to_pay', '=', $today) // La promesa relevante es para HOY
                                                          ->whereRaw('promesas.id = (
                                                              SELECT p_sub.id FROM promesas p_sub
                                                              WHERE p_sub.prestamo_id = prestamos.id AND p_sub.to_pay >= ?
                                                              ORDER BY p_sub.to_pay ASC, p_sub.id ASC LIMIT 1
                                                          )', [$today]);
                                                    });
                                                });
                                                // Caso 1.2: Hay abonos, NO hay promesa relevante, y (MAX(fecha_abono) + frecuencia.dias) <= $today
                                                $qPromesaOAbono->orWhere(function (Builder $qAbono) use ($today) {
                                                    $qAbono->whereDoesntHave('promesas', fn(Builder $p) => $p->where('to_pay', '>=', $today))
                                                           ->whereExists(function ($sub) use ($today) {
                                                               $sub->select(DB::raw(1))
                                                                   ->from('frecuencias')
                                                                   ->whereColumn('frecuencias.id', 'prestamos.frecuencia_id')
                                                                   ->whereRaw('DATE_ADD((SELECT MAX(sa.fecha_abono) FROM abonos sa WHERE sa.prestamo_id = prestamos.id), INTERVAL frecuencias.dias DAY) <= ?', [$today]);
                                                           });
                                                });
                                            });
                                    });
                                    // Sub-bloque: Casos donde NO hay abonos
                                    // Caso 2: No hay abonos, y initial_date <= $today
                                    $q->orWhere(function (Builder $qSinAbonos) use ($today) {
                                        $qSinAbonos->whereDoesntHave('abonos')
                                                   ->where('initial_date', '<=', $today);
                                    });
                                });
                        } elseif ($value === 'al_dia') {
                            return $query->where(function (Builder $qMain) use ($today) {
                                $qMain->orWhere('estado', 'finalizado'); // Los finalizados siempre están al día
                                $qMain->orWhere(function (Builder $qActivo) use ($today) {
                                    $qActivo->whereIn('estado', ['activo', 'autorizado']) // Para activos/autorizados
                                        ->where(function (Builder $q) use ($today) {
                                            // Sub-bloque: Casos donde hay abonos
                                            $q->orWhere(function(Builder $qConAbonos) use ($today) {
                                                $qConAbonos->whereHas('abonos')
                                                    ->where(function(Builder $qPromesaOAbono) use ($today) {
                                                        // Caso 1.1: Hay abonos, hay promesa relevante, y promesa.to_pay > $today
                                                        $qPromesaOAbono->orWhere(function (Builder $qPromesa) use ($today) {
                                                            $qPromesa->whereHas('promesas', function (Builder $p) use ($today) {
                                                                $p->where('to_pay', '>', $today) // La promesa relevante es para DESPUÉS de hoy
                                                                  ->whereRaw('promesas.id = (
                                                                      SELECT p_sub.id FROM promesas p_sub
                                                                      WHERE p_sub.prestamo_id = prestamos.id AND p_sub.to_pay >= ?
                                                                      ORDER BY p_sub.to_pay ASC, p_sub.id ASC LIMIT 1
                                                                  )', [$today]);
                                                            });
                                                        });
                                                        // Caso 1.2: Hay abonos, NO hay promesa relevante, y (MAX(fecha_abono) + frecuencia.dias) > $today
                                                        $qPromesaOAbono->orWhere(function (Builder $qAbono) use ($today) {
                                                            $qAbono->whereDoesntHave('promesas', fn(Builder $p) => $p->where('to_pay', '>=', $today))
                                                                   ->whereExists(function ($sub) use ($today) {
                                                                       $sub->select(DB::raw(1))
                                                                           ->from('frecuencias')
                                                                           ->whereColumn('frecuencias.id', 'prestamos.frecuencia_id')
                                                                           ->whereRaw('DATE_ADD((SELECT MAX(sa.fecha_abono) FROM abonos sa WHERE sa.prestamo_id = prestamos.id), INTERVAL frecuencias.dias DAY) > ?', [$today]);
                                                                   });
                                                        });
                                                    });
                                            });
                                            // Sub-bloque: Casos donde NO hay abonos
                                            // Caso 2: No hay abonos, y initial_date > $today
                                            $q->orWhere(function (Builder $qSinAbonos) use ($today) {
                                                $qSinAbonos->whereDoesntHave('abonos')
                                                           ->where('initial_date', '>', $today);
                                            });
                                        });
                                });
                            });
                        }
                        return $query;
                    }),
                SelectFilter::make('registrado_id')
                    ->label('Registrado Por')
                    ->relationship('registrado', 'name')
                    ->options(function () {
                        // Dynamically load users who have registered loans
                        return User::whereHas('prestamosRegistrados')->pluck('name', 'id')->toArray();
                    })
                    ->placeholder('Todos los registradores')
                    ->searchable()
                    ->visible(fn () => Auth::user()->can('prestamos.index') || Auth::user()->can('prestamosOficina.index')),
            ])
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->defaultPaginationPageOption(25)
            ->actions([
                // Botón “Activar Préstamo”
                Tables\Actions\Action::make('activar_prestamo')
                    ->label('Activar Préstamo')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Prestamo $record) =>
                        ($user->can('prestamos.view') || $user->can('activarPrestamosOficina.view')) &&
                        $record->estado === 'autorizado'
                    )
                    ->requiresConfirmation()
                    ->action(fn (Prestamo $record) => $record->update(['estado' => 'activo'])),

                // Botón “Refinanciar” (solo si NO hay refinanciación pendiente o negada)
                Tables\Actions\Action::make('refinanciar')
                        ->label('Refinanciar')
                        ->icon('heroicon-o-currency-dollar')
                        ->color('warning')
                        ->visible(fn (Prestamo $record) =>
                            Auth::user()->can('prestamosRefinanciar.view') &&
                            in_array($record->estado, ['activo', 'autorizado']) &&
                            ! $record->refinanciamientos()
                                    ->whereIn('estado', ['pendiente', 'negado'])
                                    ->exists()
                        )
                        ->form([
                            TextInput::make('valor')
                                ->label('Valor a refinanciar')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->prefix('COP'),

                            TextInput::make('interes')
                                ->label('Interés')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->prefix('%'),

                            TextInput::make('comicion')
                                ->label('Valor de seguro a cobrar')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->prefix('COP'),

                            Textarea::make('descripcion')
                                ->label('Nueva descripción del cliente')
                                ->placeholder('Opcionalmente reemplaza la descripción actual del cliente')
                                ->rows(3)
                                ->maxLength(255)
                                ->columnSpan('full'),
                        ])
                        ->requiresConfirmation()
                        ->action(function (Prestamo $record, array $data) {
                            // NOTA: ya no incrementamos $record->comicion aquí.
                            // Simplemente creamos la refinanciación y almacenamos su propia comisión.

                            // Actualizar descripción del cliente si viene
                            if (! empty(trim($data['descripcion'] ?? ''))) {
                                $record->cliente->update([
                                    'descripcion' => trim($data['descripcion']),
                                ]);
                            }

                            // Crear la refinanciación en estado 'pendiente'
                            Refinanciamiento::create([
                                'prestamo_id' => $record->id,
                                'valor'       => $data['valor'],
                                'interes'     => $data['interes'],
                                'comicion'    => $data['comicion'],
                                'estado'      => 'pendiente',
                                'comicion_borrada' => false,
                            ]);
                        }),

                Tables\Actions\Action::make('ver_refinanciacion')
                    ->label('Refinanciación Pendiente')
                    ->icon('heroicon-o-clock')
                    ->color('info')
                    ->visible(fn (Prestamo $record) =>
                        Auth::user()->can('prestamos.index') &&
                        $record->refinanciamientos()
                            ->where('estado', 'pendiente')
                            ->exists()
                    )
                    // Encabezado del modal
                    ->modalHeading('Refinanciación Pendiente')
                    // Descripción estática opcional (puede quitarse si no hace falta)
                    ->modalSubheading('Revisa los datos de la refinanciación antes de autorizar o negar')
                    // Definimos el formulario del modal para mostrar la info del Refinanciamiento
                    ->form([
                        Forms\Components\Placeholder::make('valor')
                            ->label('Valor Entregado')
                            ->content(fn (Prestamo $record) => 
                                '$ ' . number_format(
                                    optional(
                                        $record->refinanciamientos()
                                            ->where('estado', 'pendiente')
                                            ->latest()
                                            ->first()
                                    )->valor ?? 0,
                                    0,
                                    ',',
                                    '.'
                                ) . ' COP'
                            ),

                        Forms\Components\Placeholder::make('interes')
                            ->label('Interés')
                            ->content(fn (Prestamo $record) =>
                                optional(
                                    $record->refinanciamientos()
                                        ->where('estado', 'pendiente')
                                        ->latest()
                                        ->first()
                                )->interes . ' %'
                            ),

                        Forms\Components\Placeholder::make('deuda_refinanciada_interes')
                            ->label('Deuda despues de Refinanciar')
                            ->content(fn (Prestamo $record) =>
                                '$ ' . number_format(
                                    optional(
                                        $record->refinanciamientos()
                                            ->where('estado', 'pendiente')
                                            ->latest()
                                            ->first()
                                    )->deuda_refinanciada_interes ?? 0,
                                    0,
                                    ',',
                                    '.'
                                ) . ' COP'
                            ),

                Forms\Components\Placeholder::make('comicion')
                            ->label('Seguro a Cobrar')
                            ->content(fn (Prestamo $record) =>
                                '$ ' . number_format(
                                    optional(
                                        $record->refinanciamientos()
                                            ->where('estado', 'pendiente')
                                            ->latest()
                                            ->first()
                                    )->comicion ?? 0,
                                    0,
                                    ',',
                                    '.'
                                ) . ' COP'
                            ),

                        Forms\Components\Placeholder::make('estado')
                            ->label('Estado Actual')
                            ->content(fn (Prestamo $record) =>
                                optional(
                                    $record->refinanciamientos()
                                    ->where('estado', 'pendiente')
                                    ->latest()
                                    ->first()
                                )->estado
                            ),
                        Actions::make([
                            Actions\Action::make('autorizar')
                                ->label('Autorizar')
                                ->color('success')
                                ->close()
                                ->action(function (Prestamo $record) {
                                    $ref = $record->refinanciamientos()
                                                ->where('estado', 'pendiente')
                                                ->latest()
                                                ->first();
                                    if ($ref) {
                                        $ref->update(['estado' => 'autorizado']);
                                    }
                                }),
    
                            Actions\Action::make('negar')
                                ->label('Negar')
                                ->color('danger')
                                ->close()
                                ->action(function (Prestamo $record) {
                                    $ref = $record->refinanciamientos()
                                                ->where('estado', 'pendiente')
                                                ->latest()
                                                ->first();
                                    if ($ref) {
                                        $ref->update(['estado' => 'negado']);
                                    }
                                }),

                        ])
                    ])
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
            ])
            ->headerActions([])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AbonosRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPrestamos::route('/'),
            'create' => Pages\CreatePrestamo::route('/create'),
            'edit'   => Pages\EditPrestamo::route('/{record}/edit'),
        ];
    }
}
