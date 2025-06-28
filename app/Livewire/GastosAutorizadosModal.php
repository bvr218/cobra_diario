<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Gasto; // Asegúrate de que este modelo exista y sea correcto
use App\Models\User; // Asegúrate de que este modelo exista y sea correcto
use Carbon\Carbon;
use Illuminate\Support\Collection;
use App\Filament\Resources\GastoResource; // ¡IMPORTANTE! Asegúrate de que el namespace sea correcto para tu GastoResource
use Filament\Notifications\Notification; // Importar para notificaciones
use Filament\Facades\Filament; // Útil para tenerlo importado, aunque no se use directamente para getUrl en este caso

class GastosAutorizadosModal extends Component
{
    public bool $showModal = false;
    public ?int $userId = null;
    public ?string $fechaInicio = null;
    public ?string $fechaFin = null;
    public Collection $gastos; // Cambiado de $gastosAutorizados a $gastos
    public string $modalTitle = '';

    // Define los eventos que este modal "escucha" para abrirse
    protected $listeners = [
        'abrirModalGastosAutorizados' => 'openModal',
    ];

    public function mount(): void
    {
        $this->gastos = collect(); // Cambiado
    }

    /**
     * Abre el modal y carga los datos de gastos autorizados según los filtros.
     *
     * @param int $userId ID del usuario cuyos gastos se mostrarán.
     * @param string|null $fechaInicio Fecha de inicio para filtrar.
     * @param string|null $fechaFin Fecha de fin para filtrar.
     */
    public function openModal(int $userId, ?string $fechaInicio = null, ?string $fechaFin = null): void
    {
        $this->userId = $userId;
        $this->fechaInicio = $fechaInicio;
        $this->fechaFin = $fechaFin;
        $this->modalTitle = 'Detalle de Gastos'; // Título cambiado

        $this->loadGastos(); // Método renombrado
        $this->showModal = true;
    }

    /**
     * Cierra el modal y reinicia las propiedades.
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->reset(['userId', 'fechaInicio', 'fechaFin']);
        $this->gastos = collect(); // Cambiado
    }

    /**
     * Carga todos los gastos del usuario, aplicando filtros de fecha si existen.
     */
    public function loadGastos(): void // Método renombrado
    {
        // Consulta todos los gastos del usuario
        $query = Gasto::query()
                    ->where('user_id', $this->userId)
                    // Ya no filtramos por 'autorizado' aquí para mostrar todos
                    ->with(['user', 'tipoGasto']); // Precarga relaciones necesarias

        // Aplica el filtro de fechas si están presentes
        if ($this->fechaInicio && $this->fechaFin) {
            $query->whereBetween('created_at', [
                Carbon::parse($this->fechaInicio), // Usar la fecha y hora exactas
                Carbon::parse($this->fechaFin)     // Usar la fecha y hora exactas
            ]);
        }

        $this->gastos = $query->orderBy('created_at', 'desc')->get(); // Cambiado
    }

    /**
     * Autoriza un gasto específico.
     *
     * @param int $gastoId El ID del gasto a autorizar.
     */
    public function autorizarGasto(int $gastoId): void
    {
        $gasto = Gasto::find($gastoId);

        if (!$gasto) {
            Notification::make()->title('Error')->body('Gasto no encontrado.')->danger()->send();
            return;
        }

        if ($gasto->autorizado) {
            Notification::make()->title('Información')->body('Este gasto ya está autorizado.')->info()->send();
            return;
        }

        $gasto->autorizado = true;
        $gasto->save();

        Notification::make()->title('Gasto Autorizado')->body('El gasto ha sido autorizado correctamente.')->success()->send();

        $this->loadGastos(); // Recargar la lista en el modal
        
        // Emitir evento para que el componente principal (RegistroAbonos) recalcule las estadísticas
        $this->dispatch('statsUpdated');
    }

    /**
     * Redirige a la página de edición de un gasto específico en Filament.
     *
     * @param int $gastoId El ID del gasto a editar.
     */
    public function goToGastoEdit(int $gastoId): void
    {
        // Genera la URL usando el método getUrl del Recurso de Filament.
        // Esto es crucial para la integración correcta con las rutas de Filament.
        // Asegúrate de que 'edit' sea la acción correcta y 'record' el parámetro esperado.
        $url = GastoResource::getUrl('edit', ['record' => $gastoId]);

        // Cierra el modal antes de redirigir para una mejor experiencia de usuario
        $this->closeModal();

        // Redirige al usuario a la URL generada
        $this->redirect($url);
    }

    public function render()
    {
        return view('livewire.gastos-autorizados-modal');
    }
}