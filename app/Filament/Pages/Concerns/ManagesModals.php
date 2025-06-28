<?php

namespace App\Filament\Pages\Concerns;

use Filament\Notifications\Notification;

trait ManagesModals
{
    // Asume que las propiedades $usuarioSeleccionado, $filtrarPorFecha,
    // $fechaInicio, $fechaFin existen en el componente principal o en otros Traits.
    // No las declaramos aquí para evitar conflictos, solo las usamos.

    public function abrirModalPrestamosEntregadosClick(): void
    {
        $this->dispatchModalEvent('abrirModalPrestamosEntregados', 'préstamos entregados');
    }

    public function abrirModalTotalPrestadoClick(): void
    {
        $this->dispatchModalEvent('abrirModalTotalPrestado', 'total prestado');
    }

    public function abrirModalTotalPrestadoConInteresClick(): void
    {
        $this->dispatchModalEvent('abrirModalTotalPrestadoConInteres', 'total prestado con interés');
    }

    public function abrirModalComisionesRegistradasClick(): void
    {
        $this->dispatchModalEvent('abrirModalComisionesRegistradas', 'detalle de las comisiones');
    }

    public function abrirModalRecaudosRealizadosClick(): void
    {
        $this->dispatchModalEvent('abrirModalRecaudosRealizados', 'recaudos realizados', 'usuarioId');
    }

    public function abrirModalDineroRecaudadoClick(): void
    {
        $this->dispatchModalEvent('abrirModalDineroRecaudado', 'dinero recaudado', 'usuarioId');
    }

    public function abrirModalCantidadRefinanciacionesClick(): void
    {
        $this->dispatchModalEvent('abrirModalCantidadRefinanciaciones', 'cantidad de refinanciaciones', 'usuarioId', ['type' => 'cantidad']);
    }

    public function abrirModalValorTotalRefinanciacionesClick(): void
    {
        $this->dispatchModalEvent('abrirModalValorTotalRefinanciaciones', 'valor total de refinanciaciones', 'usuarioId', ['type' => 'valor_total']);
    }

    public function abrirModalValorRefinanciacionesConInteresClick(): void
    {
        $this->dispatchModalEvent('abrirModalValorRefinanciacionesConInteres', 'valor de refinanciaciones con interés', 'usuarioId', ['type' => 'valor_interes']);
    }

    public function abrirModalGastosAutorizadosClick(): void
    {
        $this->dispatchModalEvent('abrirModalGastosAutorizados', 'gastos', 'userId');
    }

    /**
     * Método auxiliar para despachar eventos de modales de manera genérica.
     */
    protected function dispatchModalEvent(string $eventName, string $bodyPart, string $idParamName = 'agenteAsignadoId', array $extraParams = []): void
    {
        // $this->usuarioSeleccionado viene del Trait ManagesUsers
        // $this->filtrarPorFecha, $this->fechaInicio, $this->fechaFin vienen de HandlesStatsCalculations
        if ($this->usuarioSeleccionado) {
            $params = [
                $idParamName => $this->usuarioSeleccionado->id,
                // CAMBIO: fechaInicio y fechaFin ya contendrán la hora si se seleccionó en el input
                'fechaInicio' => $this->filtrarPorFecha ? $this->fechaInicio : null,
                'fechaFin' => $this->filtrarPorFecha ? $this->fechaFin : null,
            ];
            $params = array_merge($params, $extraParams);
            $this->dispatch($eventName, ...$params);
        } else {
            Notification::make()
                ->title('Selecciona un usuario')
                ->body('Por favor, selecciona un usuario para ver su ' . $bodyPart . '.')
                ->warning()
                ->send();
        }
    }
}