<?php

namespace App\Livewire\Filament;

use Livewire\Component;
use App\Models\Prestamo;

class RutaClientes extends Component
{
    public $prestamos = [];

    protected $listeners = ['prestamosActualizados' => 'actualizarVista'];

    public function mount()
    {
        $this->cargarPrestamos();
    }

    public function actualizarOrden($orden)
    {
        try {
            if (empty($orden)) {
                throw new \Exception("El orden está vacío");
            }

            foreach ($orden as $index => $prestamoId) {
                $prestamo = Prestamo::find($prestamoId);

                if (!$prestamo) {
                    throw new \Exception("Préstamo con ID $prestamoId no encontrado");
                }

                // Actualizar posición en base de datos
                $prestamo->update(['posicion_ruta' => $index + 1]);
            }

            // Recargar los préstamos ya reordenados
            $this->cargarPrestamos();

            // Emitir evento para asegurar actualización del DOM del propio componente (si es necesario)
            $this->dispatch('prestamosActualizados');
            $this->dispatch('rutaGlobalmenteActualizada')->to(\App\Livewire\Filament\GenerarPago::class); // <-- AÑADIDO: Notificar a GenerarPago

        } catch (\Exception $e) {
            \Log::error("Error en actualizarOrden: " . $e->getMessage());
        }
    }

    public function actualizarVista()
    {
        // Recargar préstamos desde base de datos
        $this->cargarPrestamos();
    }

    private function cargarPrestamos()
    {
        $this->prestamos = Prestamo::where('agente_asignado', auth()->user()->id)
                                   ->whereIn('estado', ['activo', 'autorizado']) // <-- AÑADIDO: Filtrar por estado
                                   ->orderBy('posicion_ruta')
                                   ->get();
    }

    public function render()
    {
        return view('livewire.filament.ruta-clientes');
    }
}
