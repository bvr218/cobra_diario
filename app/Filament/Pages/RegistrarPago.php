<?php

namespace App\Filament\Pages;

use App\Models\Abono;
use App\Models\Cliente;
use App\Models\Promesa;
use App\Models\Prestamo;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class RegistrarPago extends Page
{
    use \Filament\Forms\Concerns\InteractsWithForms;

    // Ícono, grupo y etiquetas en la navegación
    protected static ?string $navigationIcon  = 'heroicon-o-currency-dollar';
    protected static ?string $navigationGroup = 'Gestión de Préstamos';
    protected static ?string $label           = 'Registrar Pago';
    protected static ?string $pluralLabel     = 'Registrar Pagos';
    protected static string  $view            = 'filament.pages.registrar-pago';

    // ----------------------------------------------------------------
    // 1) Restringir acceso a la ruta de la página
    // ----------------------------------------------------------------
    public static function canAccess(): bool
    {
        return Auth::user()->can('registrarPago.index') ||
               Auth::user()->can('registrarPagoOficina.index') ||
               Auth::user()->can('registrarPago.view');
    }

    // ----------------------------------------------------------------
    // 2) Restringir registro en navegación lateral
    // ----------------------------------------------------------------
    public static function shouldRegisterNavigation(): bool
    {
        return Auth::user()->can('registrarPago.index') ||
               Auth::user()->can('registrarPagoOficina.index') ||
               Auth::user()->can('registrarPago.view');
    }

    // ----------------------------------------------------------------
    //  Propiedades enlazadas al formulario
    // ----------------------------------------------------------------
    public ?int    $cliente_id    = null;
    public ?int    $prestamo_id   = null;
    public ?int    $monto_abono   = null;
    public ?string $fecha_promesa = null;
    public ?bool   $espera       = null;

    protected $messages = [
        'monto_abono.required' => 'Por favor, ingresa un monto de abono.',
    ];

    // ----------------------------------------------------------------
    //  Definición del formulario
    // ----------------------------------------------------------------
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('cliente_id')
                    ->label('Cliente')
                    ->options(function () {
                        $user = Auth::user();

                        if ($user->can('registrarPago.index')) {
                            // Todos los clientes con al menos un préstamo
                            return Cliente::whereHas('prestamos')
                                ->pluck('nombre', 'id');
                        }

                        if ($user->can('registrarPagoOficina.index')) {
                            // Clientes con préstamos en la oficina del usuario
                            return Cliente::whereHas('prestamos', function ($q) use ($user) {
                                $q->whereHas('cliente', fn($s) => $s->where('oficina_id', $user->id));
                            })->pluck('nombre', 'id');
                        }

                        // Solo clientes con préstamos asignados al agente
                        return Cliente::whereHas('prestamos', fn($q) =>
                            $q->where('agente_asignado', $user->id)
                        )->pluck('nombre', 'id');
                    })
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Seleccione un Cliente')
                    ->live()
                    ->afterStateUpdated(fn (callable $set) => $set('prestamo_id', null)),
                Select::make('prestamo_id')
                    ->label('Préstamo')
                    ->options(function (callable $get) {
                        $user = Auth::user();
                        $clienteId = $get('cliente_id');

                        if (! $clienteId) {
                            return [];
                        }

                        // Filtro base: cliente y estado del préstamo
                        $query = Prestamo::where('cliente_id', $clienteId)
                            ->whereIn('estado', ['activo', 'autorizado']);

                        // Si tiene el permiso registrarPago.view, mostrar solo préstamos donde él sea el agente asignado
                        if ($user->can('registrarPago.view')) {
                            $query->where('agente_asignado', $user->id);
                        }

                        return $query->get()
                            ->mapWithKeys(fn($p) => [
                                $p->id => "ID: {$p->id} | Deuda Inicial: $".number_format($p->deuda_inicial, 0, ',', '.')
                                    ." | Deuda Actual: $".number_format($p->deuda_actual, 0, ',', '.')
                                    ." | Cuota: $".number_format($p->monto_por_cuota, 0, ',', '.')
                                    ." | Próx. Pago: ".($p->next_payment
                                        ? \Carbon\Carbon::parse($p->next_payment)->format('d/m/Y')
                                        : 'Sin fecha'),
                            ])
                            ->toArray();
                    })
                    ->required()
                    ->searchable()
                    ->preload()
                    ->helperText('Seleccione un Préstamo')
                    ->hidden(fn($get) => ! $get('cliente_id'))
                    ->live(),
                Toggle::make('espera')
                    ->label('Promesa de pago')
                    ->live()
                    ->helperText('El cliente pidió tiempo para realizar el pago')
                    ->hidden(fn($get) => ! $get('prestamo_id')),

                TextInput::make('monto_abono')
                    ->label('Monto del Abono')
                    ->numeric()
                    ->prefix('$')
                    // ->live()
                    ->required()
                    ->helperText('Ingrese el monto del abono')
                    ->hidden(fn($get) => ! $get('prestamo_id') || $get('espera')),

                DatePicker::make('fecha_promesa')
                    ->label('¿Cuándo pagará?')
                    ->native(false)
                    ->minDate(Carbon::today())
                    ->required()
                    ->hidden(fn($get) => ! $get('prestamo_id') || ! $get('espera')),
            ]);
    }

    // ----------------------------------------------------------------
    //  Lógica de guardado
    // ----------------------------------------------------------------
    public function guardarAbono(): void
    {
        $data = $this->form->getState();

        if (isset($data['fecha_promesa']) && $data['fecha_promesa']) {
            // Crear promesa de pago
            Promesa::create([
                'prestamo_id' => $data['prestamo_id'],
                'to_pay'      => $data['fecha_promesa'],
            ]);

            Notification::make()
                ->title('Promesa registrada correctamente.')
                ->success()
                ->send();
        } else {
            // Crear abono normal
            $last = Abono::where('prestamo_id', $data['prestamo_id'])
                         ->max('numero_cuota') ?? 0;

            Abono::create([
                'prestamo_id'     => $data['prestamo_id'],
                'monto_abono'     => $data['monto_abono'],
                'fecha_abono'     => now(),
                'numero_cuota'    => $last + 1,
                'registrado_por_id' => Auth::id(),
            ]);

            Notification::make()
                ->title('Abono registrado correctamente.')
                ->success()
                ->send();
        }

        // Resetear formulario
        $this->reset(['cliente_id', 'prestamo_id', 'monto_abono', 'espera', 'fecha_promesa']);
        $this->form->fill([]);
    }
}
