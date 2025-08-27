<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\RegistroLiquidacion;
use Illuminate\Support\Carbon;
use Filament\Notifications\Notification; // Importar notificaciones

class VistaLiquidacionGuardada extends Component
{
    public ?RegistroLiquidacion $liquidacion = null;
    public array $datos = [];

    public function mount(int $recordId): void
    {
        $this->liquidacion = RegistroLiquidacion::with('user')->find($recordId);

        if (!$this->liquidacion) {
            abort(404);
        }

        $this->datos = $this->liquidacion->datos_liquidacion ?? [];
    }

    /**
     * *** NUEVO MÉTODO ***
     * Abre el modal correspondiente al cuadro clickeado.
     *
     * @param string $tipoLista La clave de la lista en el array 'listas_detalladas'.
     * @param string $titulo El título para el modal.
     */
    public function abrirModal(string $tipoLista, string $titulo): void
    {
        $listaData = $this->datos['listas_detalladas'][$tipoLista] ?? null;

        if (is_null($listaData) || empty($listaData)) {
            Notification::make()
                ->title('Sin Datos')
                ->body('No hay un listado detallado para este concepto.')
                ->info()
                ->send();
            return;
        }

        // --- INICIO DE LA MODIFICACIÓN ---
        // Obtenemos los datos necesarios para el PDF desde el modelo de la liquidación.
        $agentId = $this->liquidacion->user_id;
        $fechaDesde = Carbon::parse($this->liquidacion->desde);
        $year = $fechaDesde->year;
        $month = $fechaDesde->month;
        // --- FIN DE LA MODIFICACIÓN ---

        // Determinar a qué evento de modal despachar según el tipo de lista
        switch ($tipoLista) {
            case 'prestamos':
                $valorCampo = str_contains(strtolower($titulo), 'con interés') ? 'valor_prestado_con_interes' : 'valor_total_prestamo';
                // Añadir los nuevos parámetros al dispatch
                $this->dispatch('abrirVistaPrestamosModal', lista: $listaData, titulo: $titulo, valorCampo: $valorCampo, agentId: $agentId, year: $year, month: $month);
                break;
            case 'recaudos':
                // Añadir los nuevos parámetros al dispatch
                $this->dispatch('abrirVistaAbonosModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                break;
            case 'refinanciaciones':
                // Añadir los nuevos parámetros al dispatch
                $this->dispatch('abrirVistaRefinanciacionesModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                break;
            case 'comisiones':
                // Añadir los nuevos parámetros al dispatch
                $this->dispatch('abrirVistaComisionesModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                break;
            case 'gastos':
                // Añadir los nuevos parámetros al dispatch
                $this->dispatch('abrirVistaGastosModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                break;
            case 'prestamos_finalizados':
                // Añadir los nuevos parámetros al dispatch
                $this->dispatch('abrirVistaPrestamosFinalizadosModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                break;
            case 'ajustes_dinero':
                $this->dispatch('abrirVistaAjustesModal', lista: $listaData, titulo: $titulo, agentId: $agentId, year: $year, month: $month);
                break;
        }
    }

    public function render()
    {
        return view('livewire.vista-liquidacion-guardada')
            ->layout('filament::components.layouts.app');
    }
}