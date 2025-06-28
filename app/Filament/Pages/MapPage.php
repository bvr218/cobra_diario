<?php

namespace App\Filament\Pages;

use Filament\Forms\Components;
use Filament\Forms\Components\Card;
use Filament\Forms\Components\Livewire; // Keep this for the component itself
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\DatePicker; // Explicit import
use Filament\Forms\Components\TextInput; // Explicit import
use App\Models\Cliente;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Placeholder; 
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms; // Import HasForms
use Filament\Forms\Concerns\InteractsWithForms; // Import InteractsWithForms
use Illuminate\Support\Carbon;
 // For date handling

class MapPage extends Page implements HasForms // Implement HasForms
{
    use InteractsWithForms; // Use InteractsWithForms

    protected static ?string $navigationIcon = 'heroicon-o-map'; // Changed for relevance
    protected static ?string $navigationLabel = 'Mapa de clientes';
    protected static ?string $navigationGroup = 'Administración de Usuarios';
    protected static ?string $title = 'Mapa de clientes';

    protected static string $view = 'filament.pages.map-page';

    // Make these public so Livewire/Filament can manage their state reactively
    public $clients = [];
    public $MyClients = [];

    // Properties to hold the date state for the form
    public $all_clients_date;
    public $my_clients_date;


    public const COLORES = [
        "blanco" => "FFFFFF",
        "rojo" => "FF0000",
        "verde" => "00FF00",
        "naranja" => "FFA500",
    ];


    // Mount method for initialization
    public function mount(): void
    {
        // Set default dates for the form state properties
        $today = now()->format('Y-m-d');
        $this->all_clients_date = $today;
        $this->my_clients_date = $today;

        // Fill the form with initial default values
        $this->form->fill([
            'all_clients_date' => $this->all_clients_date,
            'my_clients_date' => $this->my_clients_date,
        ]);

        

        // Load initial client data based on default dates
       
    }


    protected function getFormSchema(): array
    {

       

        // The schema now uses the public properties which are updated reactively
        return [
            Tabs::make("Mapas")
                ->contained(false)
                ->tabs([
                    Tabs\Tab::make("Mapa de todos los clientes")
                        ->hidden(fn () => !request()->user()->can("map.index"))
                        ->schema([
                            // Give unique names to date pickers
                            Grid::make(3)
                                ->schema([
                                    Placeholder::make('end_placeholder')->label(""),
                                    DatePicker::make("all_clients_date")
                                        ->label("Fecha")
                                        ->native(false)
                                        ->displayFormat('d/m/Y')
                                        
                                        
                                        ->helperText("Ponga una fecha para revisar los clientes.")
                                        // ->default(now()->format('Y-m-d')) // Default set in mount()
                                        ->live() // Make this field reactive
                                        ->afterStateUpdated(function ($get,$state) {
                                            // $state is the new date value
                                            // $algo = $get("all_clients_date");
                                            // $this->chargeClients($state);
                                            // Force component re-render if needed (often automatic with reactive)
                                            // $this->dispatchBrowserEvent('update-map-data'); // Optional: If mapView needs explicit trigger
                                        }),
                                    Placeholder::make('end_placeholder')->label(""),


                                ]),
                            Card::make([
                                Livewire::make('mapView', [
                                    // Pass the reactive property directly. Livewire handles JSON encoding.
                                    'userList' => json_encode($this->chargeClients($this->all_clients_date)),
                                    'initialCoordinates' => "3.0788263,-74.6158671",
                                    'mapId' => 'allClientsMap' // Give maps unique IDs if needed by mapView JS
                                ])
                                ->key('all-clients-map-' . $this->all_clients_date) // Add key to force re-render
                            ]),
                        ]),
                    Tabs\Tab::make("Mapa de mi ruta")
                        ->hidden(fn () => !(
                            request()->user()->can("map.view") ||
                            request()->user()->can("mapOficina.index")
                        ))
                        ->schema([
                            Grid::make(3)
                            ->schema([
                                Placeholder::make('end_placeholder')->label(""),
                                DatePicker::make("my_clients_date")
                                    ->label("Fecha")
                                    ->native(false)
                                    ->displayFormat('d/m/Y')
                                    
                                    
                                    ->helperText("Ponga una fecha para revisar los clientes.")
                                    // ->default(now()->format('Y-m-d')) // Default set in mount()
                                    ->live() // Make this field reactive
                                    ->afterStateUpdated(function ($get,$state) {
                                        // $state is the new date value
                                        // $algo = $get("all_clients_date");
                                        // $this->chargeClients($state);
                                        // Force component re-render if needed (often automatic with reactive)
                                        // $this->dispatchBrowserEvent('update-map-data'); // Optional: If mapView needs explicit trigger
                                    }),
                                Placeholder::make('end_placeholder')->label(""),


                            ]),
                            Card::make([
                                Livewire::make('mapView', [
                                    'userList' => json_encode($this->chargeMyClients($this->my_clients_date)),
                                    'initialCoordinates' => "3.0788263,-74.6158671",
                                     'mapId' => 'myClientsMap' // Give maps unique IDs if needed by mapView JS
                                ])
                                ->key('my-clients-map-' . $this->my_clients_date) // Add key to force re-render
                            ]),
                        ]),
                ]),
        ];
    }


public function chargeMyClients($date = "")
{
    $user = request()->user();
    $targetDate = $date ? Carbon::parse($date) : Carbon::today();

    $clientesQuery = Cliente::query();

    if ($user->can('mapOficina.index')) {
        // Filtrar por la oficina del usuario
        $clientesQuery->where('oficina_id', $user->id);
    } else {
        // Filtrar por el agente asignado en los préstamos
        $clientesQuery->whereHas("prestamos", function ($q) use ($user) {
            $q->where("prestamos.agente_asignado", $user->id);
        });
    }

    // Cargar relaciones necesarias para calcular el pago
    $clientesQuery->with(['prestamos' => function ($prestamoQuery) {
        $prestamoQuery->with(['frecuencia', 'abonos', 'promesas']);
    }]);

    $clientesConPrestamos = $clientesQuery->get();

    $clientesFiltrados = $clientesConPrestamos->filter(function ($cliente) use ($targetDate) {
        foreach ($cliente->prestamos as $prestamo) {
            $nextPaymentDate = $prestamo->next_payment;
            if ($nextPaymentDate && $nextPaymentDate->toDateString() === $targetDate->toDateString()) {
                return true;
            }
        }
        return false;
    });

    $salida = [];

    foreach ($clientesFiltrados as $cliente) {
        $fotoPath = $cliente->foto_cliente ? storage_path("app/public/" . $cliente->foto_cliente) : null;
        $imageUrl = null;

        if ($fotoPath && file_exists($fotoPath) && !empty($cliente->coordenadas)) {
            $color = self::COLORES["rojo"];

            $prestamo = $cliente->getPrestamo($targetDate);
            $payment = $prestamo?->abonos()->where("fecha_abono", $targetDate)->first();

            $color = $payment ? self::COLORES["verde"] : self::COLORES["rojo"];

            $assetUrl = asset("storage/" . $cliente->foto_cliente);
            $imageUrl = config("app.url") . "/image/convert?url=" . urlencode($assetUrl) . "&color=" . $color . "&border=50";

            $salida[] = [
                "label" => $cliente->nombre,
                "image" => $imageUrl,
                "coor" => $cliente->coordenadas,
            ];
        } elseif (empty($cliente->coordenadas)) {
            Log::warning("Cliente ID {$cliente->id} ({$cliente->nombre}) no tiene coordenadas.");
        } elseif (!$fotoPath || !file_exists($fotoPath)) {
            Log::warning("Cliente ID {$cliente->id} ({$cliente->nombre}) no tiene foto válida.");
            if (!empty($cliente->coordenadas)) {
                $salida[] = [
                    "label" => $cliente->nombre . " (sin foto)",
                    "image" => null,
                    "coor" => $cliente->coordenadas,
                ];
            }
        }
    }

    return $salida;
}




    public function chargeClients($date = "") // Añadir tipo de retorno
    {
        // Usa Carbon para un manejo de fechas más robusto
        $targetDate = $date ? Carbon::parse($date) : Carbon::parse(date("Y-m-d"));

        // --- Modificación aquí ---

        // 1. Inicia la consulta para Cliente
        $clientesQuery = Cliente::query();

        // 2. Asegúrate de que el cliente tenga préstamos.
        //    Opcionalmente, podrías añadir más condiciones aquí si los préstamos
        //    tienen un estado (ej: solo préstamos 'activos')
        //    Asume que la relación en Cliente se llama 'prestamos'
        $clientesQuery->whereHas('prestamos');

        // 3. Carga previamente (Eager Load) las relaciones necesarias
        //    para calcular 'next_payment' eficientemente y evitar N+1 queries.
        $clientesQuery->with(['prestamos' => function ($prestamoQuery) {
            $prestamoQuery->with(['frecuencia', 'abonos', 'promesas']);
                // ->where('estado', 'activo'); // Replica el filtro de estado si lo usaste en whereHas
        }]);

        // 4. Obtén los clientes que cumplen el filtro inicial (tener préstamos)
        $clientesConPrestamos = $clientesQuery->get();

        // 5. Filtra la colección en PHP usando el accessor 'next_payment'
        $clientesFiltrados = $clientesConPrestamos->filter(function ($cliente) use ($targetDate) {
            // Revisa cada préstamo del cliente
            foreach ($cliente->prestamos as $prestamo) {
                $nextPaymentDate = $prestamo->next_payment;
                if (Carbon::parse($nextPaymentDate)->toDateString() === Carbon::parse($targetDate)->toDateString()) {
                    return true; // Mantener este cliente en la colección filtrada
                }
            }
            return false; // Descartar este cliente
        });

        // --- Fin de la modificación ---

        $salida = [];
        // Itera sobre los clientes que SÍ tienen un pago en la fecha objetivo
        foreach ($clientesFiltrados as $cliente) {
            // Verifica que las coordenadas y la foto existan
            $fotoPath = $cliente->foto_cliente ? storage_path("app/public/" . $cliente->foto_cliente) : null;
            // Verifica si el archivo de imagen existe realmente antes de generar la URL
            $imageUrl = null;
            if ($fotoPath && file_exists($fotoPath) && !empty($cliente->coordenadas)) {

                // Determina el color (podrías añadir lógica basada en el estado del préstamo)
                $color = self::COLORES["rojo"]; // Color por defecto

                $prestamo = $cliente->getPrestamo($targetDate);

                if ($prestamo) {
                    $payment = $prestamo->abonos()->where("fecha_abono", $targetDate)->first();
                    $color = $payment ? self::COLORES["verde"] : self::COLORES["rojo"];
                } else {
                    // Si no hay préstamo en esa fecha, asumimos rojo
                    $color = self::COLORES["rojo"];
                }


                $assetUrl = asset("storage/" . $cliente->foto_cliente);
                $imageUrl = config("app.url") . "/image/convert?url=" . urlencode($assetUrl) . "&color=" . $color . "&border=50";

                $salida[] = [
                    "label" => $cliente->nombre,
                    "image" => $imageUrl,
                    "coor" => $cliente->coordenadas, // Asegúrate que esto sea un formato útil (ej: {lat: Y, lng: X})
                ];

            } elseif (empty($cliente->coordenadas)) {
                 Log::warning("Cliente ID {$cliente->id} ({$cliente->nombre}) no tiene coordenadas.");
            } elseif (!$fotoPath || !file_exists($fotoPath)) {
                Log::warning("Cliente ID {$cliente->id} ({$cliente->nombre}) no tiene foto o la ruta es inválida: " . ($cliente->foto_cliente ?? 'null'));
                // Opcionalmente, podrías añadir un marcador sin imagen o con una imagen por defecto
                 if (!empty($cliente->coordenadas)) {
                     $salida[] = [
                         "label" => $cliente->nombre . " (sin foto)",
                         "image" => null, // O una imagen por defecto
                         "coor" => $cliente->coordenadas,
                         // Podrías añadir un color diferente o icono
                     ];
                 }
            }
        }

        // Es mejor práctica devolver el resultado en lugar de asignarlo a una propiedad pública
        return $salida;
    }

    
    // ----------------------------------------------------------------
    // 2) Restringir registro en navegación lateral
    // ----------------------------------------------------------------
    public static function shouldRegisterNavigation(): bool
    {
        return request()->user()->can('map.view');
    }

}