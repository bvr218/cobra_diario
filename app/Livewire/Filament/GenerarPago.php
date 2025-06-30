<?php

namespace App\Livewire\Filament;

use Livewire\Component;
use App\Models\Prestamo;
use App\Models\Abono;
use App\Models\Refinanciamiento; // Importar el modelo Refinanciamiento
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth; // Importar Auth para permisos
use Illuminate\Support\Facades\DB; // Importar DB para transacciones
use Illuminate\Support\Collection; // Importar la clase Collection

class GenerarPago extends Component
{
    public $position = 1;
    public $prestamo;
    public $deuda_actual = 0;
    public $monto_por_cuota = 0;
    public $next_payment;
    public $dias;
    public $deuda_inicial = 0; // Para mostrar la deuda inicial
    public $monto_abono = null; // Corregido: Inicializado a null
    public $nuevo_saldo = 0;
    public $numero_cuota = 0;
    public $totalPrestamos = 0;

    // Para la búsqueda
    public string $searchTerm = '';
    public bool $showSearchInput = false; // Nuevo: Controla la visibilidad del input de búsqueda

    // Para el filtro de estado de préstamos
    public bool $showFilterInput = false; // Controla la visibilidad del select de filtro
    public string $filterStatus = ''; // 'vencidos', 'aldia', o '' para todos

    // Para la descripción del cliente
    public ?string $cliente_descripcion = null;

    // Control de modales
    public bool $confirmandoAbono = false;
    public bool $refinanciandoPrestamo = false; // Para el modal de refinanciamiento
    public bool $mostrandoInfoFecha = false; // Para el modal de info de fecha

    // Propiedades para el formulario de refinanciamiento
    public $ref_valor = null;
    public $ref_interes = null;
    public $ref_comicion = null;
    public ?string $ref_descripcion_cliente = null;

    // Propiedad para almacenar la colección de préstamos filtrados en PHP
    protected Collection $allFilteredLoans;

    // Para el último abono y el historial
    public ?Abono $ultimoAbono = null;
    public Collection $historialAbonos;
    public bool $mostrandoHistorialAbonos = false;
    public ?string $fecha_inicio_real = null; // Para mostrar la fecha de inicio real del préstamo

    // Para la lista rápida de préstamos vencidos/al día
    public ?string $activeLoanListType = null; // 'vencidos', 'aldia', o null
    public Collection $loanList;


    protected function rules()
    {
        return [
            'monto_abono'       => 'required|numeric|min:1',
            'cliente_descripcion' => 'nullable|string|max:255',
            'ref_valor'           => 'required|numeric|min:0',
            'ref_interes'         => 'required|numeric|min:0',
            'ref_comicion'        => 'required|numeric|min:0',
            'ref_descripcion_cliente' => 'nullable|string|max:255',
        ];
    }

    protected function messages()
    {
        return [
            'monto_abono.required'      => 'Por favor ingrese un monto para abonar.',
            'monto_abono.numeric'       => 'El monto debe ser un valor numérico.',
            'monto_abono.min'           => 'No se permiten valores negativos o cero.',
            'cliente_descripcion.string' => 'La descripción debe ser texto.',
            'cliente_descripcion.max'   => 'La descripción no puede exceder los 255 caracteres.',
            'ref_valor.required'        => 'El valor a refinanciar es obligatorio.',
            'ref_valor.numeric'         => 'El valor a refinanciar debe ser numérico.',
            'ref_valor.min'             => 'El valor a refinanciar no puede ser negativo.',
            'ref_interes.required'      => 'El interés es obligatorio.',
            'ref_interes.numeric'       => 'El interés debe ser numérico.',
            'ref_interes.min'           => 'El interés no puede ser negativo.',
            'ref_comicion.required'     => 'La comisión es obligatoria.',
            'ref_comicion.numeric'      => 'La comisión debe ser numérica.',
            'ref_comicion.min'          => 'La comisión no puede ser negativa.',
            'ref_descripcion_cliente.max' => 'La descripción no puede exceder los 255 caracteres.',
        ];
    }

    protected $listeners = [
        'rutaGlobalmenteActualizada' => 'refrescarDatosPorCambioDeRuta',
    ];

    public function mount()
    {
        // Al montar el componente, cargamos la colección inicial de préstamos
        $this->historialAbonos = collect();
        $this->loanList = collect();
        // No reseteamos filterStatus aquí, para que mantenga su valor inicial (vacío)
        $this->loadAndFilterLoansCollection();
        $this->loadByPosition();
    }

    public function updated($propertyName)
    {
        // Si cambia el monto, recalcular nuevo saldo automáticamente
        if ($propertyName === 'monto_abono') {
            $this->actualizarNuevoSaldo();
        }

        // Si el término de búsqueda principal o el estado del filtro cambian, ocultar la lista rápida de préstamos.
        if (($propertyName === 'searchTerm' || $propertyName === 'filterStatus') && $this->activeLoanListType) {
            $this->activeLoanListType = null;
            $this->loanList = collect();
        }

        // Lógica para la búsqueda "ir a"
        if ($propertyName === 'searchTerm') { // Este 'searchTerm' es el del input de búsqueda principal
            $currentSearchTerm = trim($this->searchTerm);

            if (empty($currentSearchTerm)) {
                // El término de búsqueda está vacío, cargar basado en filterStatus y posición previa/actual
                $previousPrestamoId = $this->prestamo?->id;
                $this->loadAndFilterLoansCollection(); // searchTerm está vacío, carga por filterStatus

                if ($previousPrestamoId && $this->totalPrestamos > 0) {
                    $newPosition = $this->allFilteredLoans->search(fn($loan) => $loan->id === $previousPrestamoId);
                    $this->position = ($newPosition !== false) ? $newPosition + 1 : 1;
                } else {
                    $this->position = 1; // Por defecto al primer item
                }
                $this->loadByPosition(false); // preserveSearchTerm es false, searchTerm ya está vacío
                return;
            }

            // El término de búsqueda NO está vacío.
            // 1. Obtener los resultados de la búsqueda actual (esto establece $this->allFilteredLoans a los resultados de la búsqueda)
            $this->loadAndFilterLoansCollection(); // Usa $currentSearchTerm
            $firstMatchInSearch = $this->allFilteredLoans->first();

            if ($firstMatchInSearch) {
                $foundLoanId = $firstMatchInSearch->id;

                // 2. Obtener la colección base (filtrada solo por $filterStatus)
                $baseCollection = $this->getBaseCollectionForCurrentFilter();
                $actualPositionInBase = $baseCollection->search(fn($loan) => $loan->id === $foundLoanId);

                if ($actualPositionInBase !== false) {
                    $this->position = $actualPositionInBase + 1;
                } else {
                    // Fallback: Préstamo encontrado en búsqueda, pero no en la base (no debería ocurrir)
                    // Mostrar como el primero de sus propios resultados de búsqueda. $this->allFilteredLoans ya son los resultados de búsqueda.
                    $this->position = 1;
                    $this->loadByPosition(true); // Preservar término de búsqueda
                    return; // Salir temprano
                }

                // 3. Para que loadByPosition funcione con la "Ruta" y navegación correctas,
                //    $this->allFilteredLoans necesita ser la baseCollection.
                $this->allFilteredLoans = $baseCollection;
                $this->totalPrestamos = $baseCollection->count(); // Actualizar totalPrestamos para reflejar la lista base

                // 4. Cargar el préstamo. $this->position es correcto para $baseCollection.
                //    $this->searchTerm (propiedad) aún contiene $currentSearchTerm para el input.
                $this->loadByPosition(true); // preserveSearchTerm=true mantiene $currentSearchTerm en el input

            } else {
                // No se encontró préstamo para $cur rentSearchTerm.
                // $this->allFilteredLoans ya está vacío/sin coincidencias por el loadAndFilterLoansCollection() inicial.
                $this->position = 1;
                $this->loadByPosition(true); // Preservar el término de búsqueda fallido
                session()->flash('info', "No se encontraron préstamos para '{$currentSearchTerm}' con los filtros aplicados.");
            }
        }

        if ($propertyName === 'filterStatus') { // Este 'filterStatus' es el del select de filtro principal
            $currentSearchTerm = trim($this->searchTerm);
            $baseCollection = $this->getBaseCollectionForCurrentFilter(); // Obtiene la lista base con el NUEVO filterStatus

            $this->allFilteredLoans = $baseCollection; // Establecer la colección principal a la lista base
            $this->totalPrestamos = $baseCollection->count();

            if (empty($currentSearchTerm)) {
                $this->position = 1;
                $this->loadByPosition(false); // No hay término de búsqueda que preservar
            } else {
                // Hay un término de búsqueda, intentar encontrarlo en la nueva $baseCollection
                // Re-aplicar la lógica de búsqueda de `updated('searchTerm')` sobre la $baseCollection
                // Esto es un poco repetitivo, pero asegura consistencia.
                // Forzamos una re-evaluación del searchTerm como si se acabara de escribir.
                $this->updated('searchTerm'); // Llama a la lógica de searchTerm con el nuevo filterStatus ya aplicado en $baseCollection
            }
        }
    }


    protected function resetValues($resetMontoAbono = false)
    {
        $this->prestamo            = null;
        $this->deuda_actual        = 0;
        $this->monto_por_cuota     = 0;
        $this->next_payment        = null;
        $this->dias                = null;
        $this->deuda_inicial       = 0;
        if ($resetMontoAbono) {
            $this->monto_abono = null; // Corregido: Restablecer a null
        }
        $this->nuevo_saldo         = 0;
        $this->numero_cuota        = 0;
        $this->cliente_descripcion = null;
        // Resetear campos de refinanciamiento
        $this->ref_valor = null;
        $this->ref_interes = null;
        $this->ref_comicion = null;
        $this->ref_descripcion_cliente = null;
        // Resetear valores de último abono e historial
        $this->ultimoAbono = null;
        $this->historialAbonos = collect();
        $this->mostrandoHistorialAbonos = false;
        $this->mostrandoInfoFecha = false;
        $this->fecha_inicio_real = null;
        // Resetear lista rápida de préstamos
        $this->activeLoanListType = null;
        $this->loanList = collect();
    }

    /**
     * Este método carga todos los préstamos relevantes desde la DB y luego
     * los filtra en memoria (PHP) según el `searchTerm` y `filterStatus`.
     * Es crucial para que el filtro por `next_payment` funcione.
     */
    private function loadAndFilterLoansCollection(): void
    {
        // 1. Consulta base para obtener los préstamos desde la DB
        $baseQuery = Prestamo::where('agente_asignado', auth()->id())
                             ->whereIn('estado', ['activo', 'autorizado'])
                             ->with(['cliente','abonos','promesas','frecuencia','refinanciamientos'])
                             ->orderBy('posicion_ruta');

        // 2. Aplicar el término de búsqueda (ahora por nombre **o** cédula)
        if (!empty($this->searchTerm)) {
            $searchTerm = $this->searchTerm;
            $baseQuery->whereHas('cliente', function ($q) use ($searchTerm) {
                $q->where('nombre', 'like', '%' . $searchTerm . '%')
                  ->orWhere('numero_cedula', 'like', '%' . $searchTerm . '%');
            });
        }

        // 3. Obtener la colección de préstamos de la base de datos
        $loansFromDb = $baseQuery->get();

        // 4. Filtrar la colección en PHP según el estado del préstamo
        if ($this->filterStatus === 'vencidos') {
            $this->allFilteredLoans = $loansFromDb->filter(fn($p) => 
                is_null($p->next_payment) || $p->next_payment->lte(Carbon::today())
            );
        } elseif ($this->filterStatus === 'aldia') {
            $this->allFilteredLoans = $loansFromDb->filter(fn($p) => 
                !is_null($p->next_payment) && $p->next_payment->gt(Carbon::today())
            );
        } else {
            $this->allFilteredLoans = $loansFromDb;
        }

        // 5. Actualizar el total de préstamos con el conteo de la colección filtrada
        $this->totalPrestamos = $this->allFilteredLoans->count();
    }


    public function loadByPosition($preserveSearchTerm = false) {
        // Asegurarse de que la colección de préstamos filtrados esté cargada
        if (!isset($this->allFilteredLoans)) {
            $this->loadAndFilterLoansCollection();
        }

        if ($this->totalPrestamos === 0) {
            $this->resetValues(true);
            session()->flash('warning', 'No hay préstamos activos en la ruta con los filtros aplicados.');
            return;
        }

        // Mantener el término de búsqueda si estamos en modo de búsqueda
        if (!$preserveSearchTerm) {
            $this->searchTerm = ''; // Reset search term when navigating with prev/next or on initial load
        }

        // Ajustar posición para que siempre esté entre 1 y totalPrestamos
        if ($this->position > $this->totalPrestamos) {
            $this->position = $this->totalPrestamos;
        }
        $this->position = max(1, $this->position);

        // Obtener el préstamo de la colección filtrada y ya ordenada por 'posicion_ruta'
        $this->prestamo = $this->allFilteredLoans->values()->get($this->position - 1);


        if ($this->prestamo) {
            $this->deuda_actual        = $this->prestamo->deuda_actual;
            $this->monto_por_cuota     = $this->prestamo->monto_por_cuota;
            // Usar el accesor next_payment directamente del modelo
            $this->next_payment        = optional($this->prestamo->next_payment)->format('Y-m-d');

            // Cargar descripción del cliente
            $this->cliente_descripcion = $this->prestamo->cliente->descripcion ?? '';

            // Calcular días vencidos/faltantes
            $hoy         = Carbon::today();
            // Accede al accesor directamente
            $fechaPago   = $this->prestamo->next_payment ?? $hoy;
            $this->dias  = $hoy->diffInDays($fechaPago, false);

            // Lógica para determinar la deuda inicial a mostrar
            $ultimoRefinanciamientoAutorizado = $this->prestamo->refinanciamientos
                                                    ->where('estado', 'autorizado')
                                                    ->sortByDesc('id')
                                                    ->first();

            if ($ultimoRefinanciamientoAutorizado) {
                // Si hay un refinanciamiento autorizado, la "deuda inicial" a mostrar es la deuda_refinanciada_interes del último.
                $this->deuda_inicial = $ultimoRefinanciamientoAutorizado->deuda_refinanciada_interes ?? 0;
            } else {
                // Si no hay refinanciamientos, la "deuda inicial" es el valor del préstamo con interés original.
                $this->deuda_inicial = $this->prestamo->valor_prestado_con_interes ?? 0;
            }

            // Siguiente número de cuota
            $maxCuota           = Abono::where('prestamo_id', $this->prestamo->id)->max('numero_cuota');
            $this->numero_cuota = $maxCuota ? $maxCuota + 1 : 1;

            // Calcular nuevo saldo
            $this->actualizarNuevoSaldo();

            // Cargar último abono e historial de abonos
            if ($this->prestamo->relationLoaded('abonos')) {
                $this->ultimoAbono = $this->prestamo->abonos->sortByDesc('created_at')->first();
                $this->historialAbonos = $this->prestamo->abonos->sortByDesc('created_at');
            }

            // Cargar fecha de inicio real (para el modal informativo)
            $this->prestamo->loadMissing('refinanciamientos');
            $ultimoRefinanciamiento = $this->prestamo->refinanciamientos->sortByDesc('created_at')->first();

            if ($ultimoRefinanciamiento) {
                $this->fecha_inicio_real = $ultimoRefinanciamiento->created_at->format('d/m/Y H:i');
            } else {
                $this->fecha_inicio_real = $this->prestamo->created_at->format('d/m/Y H:i');
            }

        } else {
            // Casos raros: si no se encontró préstamo, resetear y forzar recarga
            $this->resetValues(true);
            session()->flash('warning', 'No se encontró un préstamo en la posición actual con los filtros aplicados.');

            if ($this->totalPrestamos > 0 && $this->position !== 1) {
                $this->position = 1;
                $this->loadByPosition();
            }
        }
    }

    public function buscarCliente()
    {
        // La lógica de búsqueda ahora se maneja en updated('searchTerm')
        // llamando a loadAndFilterLoansCollection() y luego loadByPosition().
        // Este método ya no necesita una lógica compleja, solo asegura la recarga.
        if (empty($this->searchTerm)) {
            if (!$this->prestamo && $this->totalPrestamos > 0) $this->position = 1;
            $this->loadByPosition(true); // preserveSearchTerm = true
            return;
        }
        // Si el término de búsqueda no está vacío, 'updated' ya manejó la recarga.
        // Solo necesitamos asegurar que el usuario vea un mensaje si no se encuentra.
        if ($this->totalPrestamos === 0) {
            session()->flash('info', "No se encontraron préstamos para el cliente '{$this->searchTerm}' con los filtros aplicados.");
        }
    }

    public function toggleSearchInput()
    {
        // Si el input de búsqueda está visible y se va a ocultar, y hay un término de búsqueda
        if ($this->showSearchInput && !empty(trim($this->searchTerm))) {
            $this->executeGoToSearchResult();
        }

        // Si la lista rápida de préstamos estaba activa, ocultarla.
        if ($this->activeLoanListType) {
            $this->activeLoanListType = null;
            $this->loanList = collect();
        }


        $this->showSearchInput = !$this->showSearchInput;
        $this->showFilterInput = false;

        // Si después de todo, el input se cerró y el término de búsqueda estaba vacío (o se vació por executeGoToSearchResult sin encontrar nada)
        // Aseguramos que se muestre el cliente correcto en la posición actual de la lista completa.
        if (!$this->showSearchInput && empty(trim($this->searchTerm))) {
             $this->loadAndFilterLoansCollection(); // Recarga la lista completa (sin searchTerm)
             $this->loadByPosition(false); // Carga el cliente en la $this->position actual, searchTerm ya está vacío
        }
    }

    public function clearSearch()
    {
        if (!empty(trim($this->searchTerm))) {
            $this->executeGoToSearchResult();
        } else {
            // Si searchTerm ya estaba vacío, simplemente limpia y recarga la lista completa en la posición 1
            $this->searchTerm = '';
            $this->loadAndFilterLoansCollection();
            $this->position = 1;
            $this->loadByPosition(false);
        }
        $this->showSearchInput = false; // Ocultar el input
    }

    protected function executeGoToSearchResult()
    {
        $currentSearchTermValue = trim($this->searchTerm);
        // Asegura que $allFilteredLoans se base en el término de búsqueda actual
        $this->loadAndFilterLoansCollection(); // Usa $this->searchTerm (que es $currentSearchTermValue)
        $foundLoan = $this->allFilteredLoans->first();

        $this->searchTerm = ''; // Limpiar el término de búsqueda para el estado final
        // Recargar la colección completa (ahora $this->searchTerm está vacío), respetando $filterStatus
        $this->loadAndFilterLoansCollection();

        if ($foundLoan) {
            $foundLoanId = $foundLoan->id;
            $newPosition = $this->allFilteredLoans->search(fn($loan) => $loan->id === $foundLoanId);
            $this->position = ($newPosition !== false) ? $newPosition + 1 : 1;
        } else {
            // Si no se encontró nada con el término de búsqueda original
            $this->position = 1; // Ir al inicio de la lista (ahora completa)
            if (!empty($currentSearchTermValue)) { // Solo mostrar mensaje si había un término de búsqueda real
                session()->flash('info', "No se encontraron préstamos para '{$currentSearchTermValue}'. Mostrando lista completa.");
            }
        }
        // Cargar el préstamo en la posición determinada. preserveSearchTerm es false porque $this->searchTerm ya se limpió.
        $this->loadByPosition(false);
    }

    private function getBaseCollectionForCurrentFilter(): Collection
    {
        $originalSearchTerm = $this->searchTerm; // Guardar término de búsqueda actual
        $this->searchTerm = '';                   // Limpiarlo temporalmente
        $this->loadAndFilterLoansCollection();    // Cargar colección basada solo en filterStatus
        $baseCollection = $this->allFilteredLoans; // Obtener la colección cargada
        $this->searchTerm = $originalSearchTerm;  // Restaurar término de búsqueda original
        // No es necesario recargar $this->allFilteredLoans con el searchterm aquí,
        // la lógica que llama a este método lo hará si es necesario.
        return $baseCollection;
    }
    // Nuevo método para alternar la visibilidad del filtro
    public function toggleFilterInput()
    {
        // Si la lista rápida de préstamos estaba activa, ocultarla.
        if ($this->activeLoanListType) {
            $this->activeLoanListType = null;
            $this->loanList = collect();
        }

        $this->showFilterInput = !$this->showFilterInput;
        // Al abrir/cerrar el filtro, asegurar que la búsqueda esté cerrada
        $this->showSearchInput = false;
        // No necesitamos resetear filterStatus aquí.
        // La lógica de filtrado se maneja en updated('filterStatus')
        // y en loadAndFilterLoansCollection() basado en el valor actual de $this->filterStatus.
    }

    // Nuevo método para limpiar el filtro SIN cerrar el select.
    // Esto se llamará cuando se presione la "X" en la vista.
    public function clearFilter()
    {
        $this->filterStatus = ''; // Vaciar el filtro
        $this->loadAndFilterLoansCollection(); // Recargar la colección para mostrar todos
        $this->position = 1; // Resetear la posición
        $this->loadByPosition(); // Recargar los datos de la posición actual
    }

    public function prev()
    {
        if ($this->totalPrestamos === 0) {
            return;
        }

        if ($this->position > 1) {
            $this->position--;
        } else {
            // Opcional: Si llegamos al principio, volver al final
            $this->position = $this->totalPrestamos;
        }
        $this->loadByPosition();
    }

    public function next()
    {
        if ($this->totalPrestamos === 0) {
            return;
        }

        if ($this->position >= $this->totalPrestamos) {
            $this->position = 1;
        } else {
            $this->position++;
        }
        $this->loadByPosition();
    }

    public function refrescarDatosPorCambioDeRuta()
    {
        // Al actualizar la ruta globalmente, recargamos y filtramos la colección
        $this->loadAndFilterLoansCollection();
        $this->position = 1; // Resetear la posición a 1
        $this->loadByPosition();
    }

    protected function actualizarNuevoSaldo()
    {
        // Se asegura de que $monto_abono sea un número antes de la resta.
        // Si es null, se tratará como 0 para el cálculo, lo que es correcto.
        $this->nuevo_saldo = max($this->deuda_actual - ($this->monto_abono ?? 0), 0);
    }

    /**
     * Se invoca cuando el usuario hace clic en "Guardar Abono" para abrir el modal.
     * Primero valida los campos, si son válidos, recalcula $nuevo_saldo y luego activa la bandera para mostrar el modal.
     */
    public function iniciarConfirmacion()
    {
        // 1. Validar los campos primero. Si falla, Livewire detiene la ejecución
        // y muestra los errores en el frontend.
        $this->validateOnly('monto_abono');

        // 2. Si la deuda ya está a cero, no permitir el abono ni abrir el modal
        if ($this->deuda_actual <= 0) {
            session()->flash('warning', 'El préstamo ya está finalizado. No se pueden registrar más pagos.');
            $this->confirmandoAbono = false; // Asegurarse de que el modal esté cerrado por si acaso
            return; // Detener la ejecución aquí
        }

        // 3. Si la validación pasó y hay deuda, recalcular el saldo y abrir el modal
        $this->actualizarNuevoSaldo();
        $this->confirmandoAbono = true;
    }

    public function cancelarConfirmacion()
    {
        $this->confirmandoAbono = false;
    }

    public function guardar()
    {
        // Esta validación ya se hizo en iniciarConfirmacion(), pero se mantiene la verificación de deuda actual
        // por si acaso, aunque el botón de guardar está en el modal que solo se abre si ya se validó.
        if ($this->deuda_actual <= 0) {
            session()->flash('warning', 'El préstamo ya está finalizado. No se pueden registrar más pagos.');
            $this->confirmandoAbono = false;
            return;
        }

        // NOTA: La validación principal del monto_abono se hace en iniciarConfirmacion().
        // No es necesario llamar a $this->validateOnly('monto_abono'); aquí de nuevo.

        try {
            \DB::transaction(function () {
                Abono::create([
                    'prestamo_id'  => $this->prestamo->id,
                    'monto_abono'  => $this->monto_abono,
                    'fecha_abono'  => now(),
                    'numero_cuota' => $this->numero_cuota,
                ]);

                // Después de guardar el abono, es CRUCIAL recargar la colección
                // para que los filtros y la navegación reflejen los cambios
                // (por ejemplo, si el préstamo pasa a estar "al día" o se finaliza).
                $this->loadAndFilterLoansCollection();

                session()->flash('success', '¡Abono registrado correctamente!');

                $this->monto_abono = null; // Asegúrate de que se restablezca a null

                // Después de guardar y recargar la colección, decidir cómo proceder:
                if ($this->totalPrestamos > 0) {
                    // Si todavía hay préstamos, avanzar al siguiente.
                    // El método next() se encarga de actualizar la posición y llamar a loadByPosition().
                    $this->next();
                } else {
                    // Si no quedan préstamos (ej. el último fue pagado),
                    // llamar a loadByPosition para que resetee los valores y muestre el mensaje adecuado.
                    $this->loadByPosition();
                }
            });

        } catch (\Exception $e) {
            \Log::error("Error al guardar abono: " . $e->getMessage());
            session()->flash('error', 'Hubo un error al registrar el abono.');
        }

        // Cerrar el modal (independientemente de éxito o error)
        $this->confirmandoAbono = false;
    }

    public function guardarClienteDescripcion()
    {
        if (!$this->prestamo || !$this->prestamo->cliente) {
            return;
        }

        $this->validateOnly('cliente_descripcion');

        try {
            $this->prestamo->cliente->descripcion = $this->cliente_descripcion;
            $this->prestamo->cliente->save();
            session()->flash('success_desc', 'Descripción del cliente actualizada.');
        } catch (\Exception $e) {
            \Log::error("Error al guardar descripción del cliente para préstamo ID {$this->prestamo->id}: " . $e->getMessage());
            session()->flash('error_desc', 'Hubo un error al actualizar la descripción del cliente.');
        }
    }

    // --- Métodos para Refinanciamiento ---

    public function getPuedeRefinanciarProperty(): bool
    {
        if (!$this->prestamo || !Auth::user()->can('prestamosRefinanciar.view')) {
            return false;
        }

        if (!in_array($this->prestamo->estado, ['activo', 'autorizado'])) {
            return false;
        }

        // Asegurarse de que la relación esté cargada
        if (!$this->prestamo->relationLoaded('refinanciamientos')) {
            $this->prestamo->load('refinanciamientos');
        }

        return !$this->prestamo->refinanciamientos()
            ->whereIn('estado', ['pendiente', 'negado'])
            ->exists();
    }

    public function abrirModalRefinanciamiento()
    {
        if (!$this->puede_refinanciar) {
            session()->flash('warning', 'Este préstamo no se puede refinanciar en este momento.');
            return;
        }
        $this->reset(['ref_valor', 'ref_interes', 'ref_comicion', 'ref_descripcion_cliente']);
        $this->refinanciandoPrestamo = true;
    }

    public function cancelarRefinanciamiento()
    {
        $this->refinanciandoPrestamo = false;
        $this->reset(['ref_valor', 'ref_interes', 'ref_comicion', 'ref_descripcion_cliente']);
    }

    public function guardarRefinanciamiento()
    {
        $this->validate([
            'ref_valor'           => 'required|numeric|min:0',
            'ref_interes'         => 'required|numeric|min:0',
            'ref_comicion'        => 'required|numeric|min:0',
            'ref_descripcion_cliente' => 'nullable|string|max:255',
        ]);

        try {
            DB::transaction(function () {
                if (!empty(trim($this->ref_descripcion_cliente ?? ''))) {
                    $this->prestamo->cliente->update(['descripcion' => trim($this->ref_descripcion_cliente)]);
                }

                Refinanciamiento::create([
                    'prestamo_id' => $this->prestamo->id,
                    'valor'       => $this->ref_valor,
                    'interes'     => $this->ref_interes,
                    'comicion'    => $this->ref_comicion,
                    'estado'      => 'pendiente', // Siempre se crea como pendiente
                    'comicion_borrada' => false,
                ]);
            });
            session()->flash('success', 'Solicitud de refinanciamiento creada. Pendiente de autorización.');
            $this->cancelarRefinanciamiento();
            $this->loadByPosition(); // Recargar para reflejar el estado del préstamo
        } catch (\Exception $e) {
            \Log::error("Error al guardar refinanciamiento: " . $e->getMessage());
            session()->flash('error', 'Hubo un error al crear la solicitud de refinanciamiento.');
        }
    }

    // --- Métodos para Historial de Abonos ---
    public function toggleHistorialAbonos()
    {
        $this->mostrandoHistorialAbonos = !$this->mostrandoHistorialAbonos;
    }

    public function toggleInfoFecha()
    {
        $this->mostrandoInfoFecha = !$this->mostrandoInfoFecha;
    }

    // --- Métodos para la lista rápida de Préstamos Vencidos/Al Día ---

    public function toggleLoanList(string $type)
    {
        if ($this->activeLoanListType === $type) {
            $this->activeLoanListType = null; // Desactivar si se hace clic en el mismo botón
            $this->loanList = collect();
        } else {
            $this->activeLoanListType = $type;
            $this->loadLoansForList($type);
            // Asegurarse de que otros pop-ups (búsqueda, filtro principal) estén cerrados
            $this->showSearchInput = false;
            $this->showFilterInput = false;
        }
    }

    public function closeLoanListModal()
    {
        $this->activeLoanListType = null;
        $this->loanList = collect(); // Limpiar la lista al cerrar el modal
    }


    protected function loadLoansForList(string $type)
    {
        $query = Prestamo::where('agente_asignado', auth()->id())
                         ->whereIn('estado', ['activo', 'autorizado'])
                         ->with(['cliente', 'abonos', 'frecuencia']) // Asegurar relaciones para 'next_payment' y datos de tabla
                         ->orderBy('posicion_ruta');

        $loansFromDb = $query->get();

        if ($type === 'vencidos') {
            $this->loanList = $loansFromDb->filter(function (Prestamo $prestamo) {
                $nextPayment = $prestamo->next_payment;
                return is_null($nextPayment) || $nextPayment->lte(Carbon::today());
            });
        } elseif ($type === 'aldia') {
            $this->loanList = $loansFromDb->filter(function (Prestamo $prestamo) {
                $nextPayment = $prestamo->next_payment;
                return !is_null($nextPayment) && $nextPayment->gt(Carbon::today());
            });
        } else {
            $this->loanList = collect();
        }
    }

    public function selectLoanFromListAndNavigate(int $loanId)
    {
        // No modificar $this->filterStatus ni $this->searchTerm aquí.
        // Se usarán los valores actuales del filtro y búsqueda principal.

        $this->loadAndFilterLoansCollection(); // Carga la colección basada en el $this->filterStatus y $this->searchTerm actuales.

        $newPositionInFilteredList = $this->allFilteredLoans->search(fn($loan) => $loan->id === $loanId);

        if ($newPositionInFilteredList !== false) {
            $this->position = $newPositionInFilteredList + 1;
            $this->loadByPosition(true); // Cargar el préstamo, preservar searchTerm en UI.
        } else {
            session()->flash('info', 'El préstamo seleccionado no está visible con los filtros principales y término de búsqueda actuales.');
            // Recargar la posición actual de la lista principal, preservando el searchTerm.
            $this->loadByPosition(true);
        }

        $this->closeLoanListModal(); // Cierra el modal y limpia sus variables asociadas.
    }

    public function render()
    {
        return view('livewire.filament.generar-pago');
    }
}