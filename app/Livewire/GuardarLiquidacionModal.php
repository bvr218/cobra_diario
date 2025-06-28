<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Notifications\Notification;
use App\Models\RegistroLiquidacion;
use Illuminate\Support\Carbon;
use App\Models\User; // Necesario para la validación y el user_id

class GuardarLiquidacionModal extends Component
{
    public bool $showModal = false;
    public ?string $liquidacionNombre = null;
    public ?int $usuarioId = null; // Para recibir el ID del usuario
    public ?string $fechaInicio = null; // Para recibir la fecha de inicio
    public ?string $fechaFin = null;   // Para recibir la fecha de fin

    // Reglas de validación para el nombre de la liquidación
    protected array $rules = [
        'liquidacionNombre' => 'required|string|max:50',
        'usuarioId' => 'required|exists:users,id', // Asegura que el usuario exista
        'fechaInicio' => 'required|date',
        'fechaFin' => 'required|date|after_or_equal:fechaInicio',
    ];

    // Mensajes de error personalizados para la validación
    protected array $messages = [
        'liquidacionNombre.required' => 'El nombre de la liquidación es obligatorio.',
        'liquidacionNombre.max' => 'El nombre no puede exceder los 50 caracteres.',
        'usuarioId.required' => 'No hay un usuario seleccionado para la liquidación.',
        'usuarioId.exists' => 'El usuario seleccionado no es válido.',
        'fechaInicio.required' => 'La fecha de inicio es obligatoria para guardar la liquidación.',
        'fechaFin.required' => 'La fecha de fin es obligatoria para guardar la liquidación.',
        'fechaFin.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
    ];

    // Listeners: escucha un evento para abrir el modal
    protected $listeners = [
        'openGuardarLiquidacionModal' => 'openModal',
    ];

    public function openModal(?int $usuarioId, ?string $fechaInicio, ?string $fechaFin): void
    {
        // Asigna las propiedades recibidas
        $this->usuarioId = $usuarioId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->liquidacionNombre = null; // Reinicia el nombre cada vez que se abre

        // Validar antes de mostrar el modal (esto es solo una validación previa para UX)
        if (!$this->usuarioId) {
            Notification::make()
                ->title('Error')
                ->body('No se ha recibido un usuario válido para guardar la liquidación.')
                ->danger()
                ->send();
            return;
        }

        // Si se va a guardar una liquidación, se espera un rango de fechas.
        // Aquí puedes hacer una validación más específica si es necesario.
        if (!$this->fechaInicio || !$this->fechaFin) {
             Notification::make()
                ->title('Error')
                ->body('Debe haber un rango de fecha y hora definido para guardar la liquidación.')
                ->danger()
                ->send();
            return;
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['liquidacionNombre', 'usuarioId', 'fechaInicio', 'fechaFin']); // Limpia todo al cerrar
    }

    public function guardarLiquidacion(): void
    {
        // Realiza la validación. Las reglas están en la propiedad $rules.
        $this->validate();

        try {
            RegistroLiquidacion::create([
                'nombre' => $this->liquidacionNombre,
                'user_id' => $this->usuarioId,
                'desde' => Carbon::parse($this->fechaInicio),
                'hasta' => Carbon::parse($this->fechaFin),
            ]);

            Notification::make()
                ->title('Liquidación Guardada')
                ->body('La liquidación ha sido guardada exitosamente.')
                ->success()
                ->send();

            $this->closeModal();
            // Opcional: Emitir un evento para que RegistroAbonos sepa que se guardó
            // y pueda, por ejemplo, recalcular estadísticas o limpiar su estado.
            $this->dispatch('liquidacionGuardada');

        } catch (\Exception $e) {
            Notification::make()
                ->title('Error al guardar liquidación')
                ->body('Ocurrió un error: ' . $e->getMessage())
                ->danger()
                ->send();
            \Log::error('Error al guardar liquidación en modal: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.guardar-liquidacion-modal');
    }
}