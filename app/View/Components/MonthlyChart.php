<?php

namespace App\View\Components;

use Illuminate\View\Component;

class MonthlyChart extends Component
{
    public string $chartId;
    public string $type;
    public array $chartData;

    /**
     * Create a new component instance.
     *
     * @param string $type El tipo de gráfico ('daily' o 'comparison').
     * @param array $chartData Los datos iniciales para el gráfico.
     * @param string|null $chartId Un ID único para el gráfico.
     * @return void
     */
    public function __construct(string $type, array $chartData, string $chartId = null)
    {
        $this->type = $type;
        $this->chartData = $chartData;
        // Si no se provee un ID, se genera uno aleatorio para evitar colisiones.
        $this->chartId = $chartId ?? 'chart-' . uniqid();
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.monthly-chart');
    }
}