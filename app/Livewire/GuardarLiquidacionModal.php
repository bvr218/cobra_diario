<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Notifications\Notification;
use App\Models\RegistroLiquidacion;
use App\Models\DineroBase;
use App\Models\HistorialMovimiento;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GuardarLiquidacionModal extends Component
{
    public bool $showModal = false;
    public ?string $liquidacionNombre = null;

    public ?User $usuario = null;
    public ?string $fechaInicio = null;
    public ?string $fechaFin = null;
    public ?string $rolSeleccionado = null;
    public array $stats = [];
    public array $listas = [];
    public string $type = 'diario';

    protected array $rules = [
        'liquidacionNombre' => 'required|string|max:50',
    ];

    protected array $messages = [
        'liquidacionNombre.required' => 'El nombre de la liquidación es obligatorio.',
        'liquidacionNombre.max' => 'El nombre no puede exceder los 50 caracteres.',
    ];

    protected $listeners = [
        'openGuardarLiquidacionModal' => 'openModal',
    ];

    public function openModal(
        User $usuario,
        ?string $fechaInicio,
        ?string $fechaFin,
        ?string $rolSeleccionado,
        array $stats,
        array $listas,
        string $type = 'diario'
    ): void
    {
        if (!$fechaInicio || !$fechaFin || empty($stats)) {
             Notification::make()
                ->title('Error de Datos')
                ->body('Faltan datos esenciales (fechas o estadísticas) para guardar la liquidación.')
                ->danger()
                ->send();
            return;
        }

        $this->usuario = $usuario;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->rolSeleccionado = $rolSeleccionado;
        $this->stats = $stats;
        $this->listas = $listas;
        $this->type = $type;
        $this->liquidacionNombre = null;

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['liquidacionNombre', 'usuario', 'fechaInicio', 'fechaFin', 'rolSeleccionado', 'stats', 'listas', 'type']);
    }

    public function guardarLiquidacion(): void
    {
        $this->validate();

        try {
            DB::transaction(function () {
                $dineroBase = DineroBase::where('user_id', $this->usuario->id)->first();

                // === INICIO DE LA NUEVA LÓGICA DE TRANSFERENCIA Y AJUSTE ===
                if ($dineroBase) {
                    $dineroEnManoOperaciones = $dineroBase->monto;
                    $dineroEnCajaAcumulado = $dineroBase->dinero_en_mano;

                    // Guardamos los estados originales para el historial
                    $estadoAntes = ['monto_mano' => $dineroEnManoOperaciones, 'monto_caja' => $dineroEnCajaAcumulado];

                    if ($dineroEnManoOperaciones > 0) {
                        // Caso 1: Dinero en mano es POSITIVO. Se transfiere todo a la caja.
                        $dineroBase->dinero_en_mano += $dineroEnManoOperaciones;
                        $dineroBase->monto = 0;
                        $dineroBase->save();

                        HistorialMovimiento::create([
                            'user_id' => $this->usuario->id,
                            'tipo' => 'transferencia_cierre_positivo',
                            'descripcion' => "Transferencia de Mano a Caja al guardar liquidación '{$this->liquidacionNombre}'.",
                            'monto' => $dineroEnManoOperaciones,
                            'referencia_id' => $dineroBase->id,
                            'tabla_origen' => 'dinero_bases',
                            'es_edicion' => true,
                            'fecha' => now(),
                            'cambio_desde' => json_encode($estadoAntes),
                            'cambio_hacia' => json_encode(['monto_mano' => 0, 'monto_caja' => $dineroBase->dinero_en_mano]),
                        ]);

                    } elseif ($dineroEnManoOperaciones < 0) {
                        // Caso 2: Dinero en mano es NEGATIVO. Se intenta cubrir con la caja.
                        $montoACubrir = abs($dineroEnManoOperaciones);
                        $cubiertoPorCaja = min($montoACubrir, $dineroEnCajaAcumulado);

                        if ($cubiertoPorCaja > 0) {
                            $dineroBase->dinero_en_mano -= $cubiertoPorCaja; // Se resta de la caja
                            $dineroBase->monto += $cubiertoPorCaja;         // Se suma al dinero en mano (haciéndolo menos negativo)
                            $dineroBase->save();

                            HistorialMovimiento::create([
                                'user_id' => $this->usuario->id,
                                'tipo' => 'transferencia_cierre_negativo',
                                'descripcion' => "Compensación de Mano con Caja al guardar liquidación '{$this->liquidacionNombre}'.",
                                'monto' => $cubiertoPorCaja,
                                'referencia_id' => $dineroBase->id,
                                'tabla_origen' => 'dinero_bases',
                                'es_edicion' => true,
                                'fecha' => now(),
                                'cambio_desde' => json_encode($estadoAntes),
                                'cambio_hacia' => json_encode(['monto_mano' => $dineroBase->monto, 'monto_caja' => $dineroBase->dinero_en_mano]),
                            ]);
                        }
                    }
                }
                // === FIN DE LA NUEVA LÓGICA ===

                // Ahora guardamos el registro de liquidación con los datos calculados
                $datosParaGuardar = array_merge(
                    [
                        'nombre_usuario' => $this->usuario->name,
                        'rol' => $this->rolSeleccionado,
                        'fecha_guardado' => now()->toDateTimeString(),
                    ],
                    $this->stats,
                    ['listas_detalladas' => $this->listas]
                );

                RegistroLiquidacion::create([
                    'nombre' => $this->liquidacionNombre,
                    'user_id' => $this->usuario->id,
                    'desde' => Carbon::parse($this->fechaInicio),
                    'hasta' => Carbon::parse($this->fechaFin),
                    'datos_liquidacion' => $datosParaGuardar,
                    'type' => $this->type,
                ]);
            });

            Notification::make()
                ->title('Liquidación Guardada y Procesada')
                ->body('La liquidación se guardó y los balances de dinero fueron ajustados exitosamente.')
                ->success()
                ->send();

            $this->closeModal();
            $this->dispatch('liquidacionGuardada');

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al Guardar Liquidación')
                ->body('Ocurrió un error inesperado: ' . $e->getMessage())
                ->danger()
                ->send();
            Log::error("Error al guardar liquidación o transferir dinero: " . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.guardar-liquidacion-modal');
    }
}