<?php

namespace App\Filament\Pages\Concerns;

use App\Models\User;
use Illuminate\Support\Collection;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon; // Importar Carbon si se usa en este trait

trait ManagesUsers
{
    // Propiedades de estado relacionadas con la gestión de usuarios
    public ?string $rolSeleccionado = null;
    
    // CAMBIO CLAVE AQUÍ: Declaración única de $usuarios como una colección vacía por defecto
    public Collection $usuarios; 
    
    public ?User $usuarioSeleccionado = null;
    public bool $showList = false;
    public string $search = '';

    // Método de inicialización (llamado desde el mount del componente principal)
    public function initializeManagesUsers(): void
    {
        // Inicializa la colección de usuarios aquí, en lugar de en la declaración de la propiedad.
        // Esto es más seguro con tipado de propiedades.
        $this->usuarios = collect(); 
    }

    // Asegúrate de que estos métodos sean PUBLICOS si van a ser llamados directamente
    // por Livewire (ej. wire:click="setActiveRol('Oficina')") o desde el componente principal.

    public function setActiveRol(string $rol): void
    {
        $this->rolSeleccionado = $rol;
        $this->usuarioSeleccionado = null; // Deselecciona el usuario al cambiar de rol
        $this->search = ''; // Limpia la búsqueda
        $this->showList = true; // Muestra la lista para elegir nuevo usuario

        // Asegúrate de que estas propiedades existan en el componente principal o en HandlesStatsCalculations
        // y sean public. No las declares aquí para evitar el conflicto.
        $this->resetStats();
        // Queremos que "Día Individual" sea el default al cambiar de rol y seleccionar un usuario nuevo
        $this->filtrarPorFecha = true;
        // Las fechas se inicializarán con initializeDatesBasedOnFilter() en el componente principal
        // o cuando se seleccione un usuario.
        $this->fechaInicio = null;
        $this->fechaFin = null;
        $this->dispatch('updateChartData', usuarioSeleccionadoId: null, rolSeleccionado: null);
        $this->refreshUsuarios(); // Recarga la lista de usuarios para el nuevo rol
    }

    public function updatedSearch(): void
    {
        $this->refreshUsuarios();
    }

    public function refreshUsuarios(): void
    {
        // Asegúrate de que $this->rolSeleccionado no sea null antes de usar `role()`
        $query = User::query();
        if ($this->rolSeleccionado) {
            $query->role($this->rolSeleccionado);
        } else {
            // Si no hay rol seleccionado, no muestres usuarios o maneja este caso
            $this->usuarios = collect();
            return;
        }
        
        if (strlen($this->search) > 0) {
            $query->where('name', 'like', "%{$this->search}%");
        }
        $this->usuarios = $query->get();
    }

    public function selectUsuario(int $id): void
    {
        $this->usuarioSeleccionado = User::find($id);
        if ($this->usuarioSeleccionado) {
            // Asegúrate de establecer el rol seleccionado según el usuario elegido
            $this->rolSeleccionado = $this->usuarioSeleccionado->hasRole('oficina') ? 'Oficina' : ($this->usuarioSeleccionado->hasRole('agente') ? 'Agente' : null);
            $this->showList = false; // Cierra la lista
            
            // Establece "Día Individual" como predeterminado y reinicia estadísticas
            $this->resetStats(); 
            $this->filtrarPorFecha = true; // Asegura que "Día Individual" esté activo
            // initializeDatesBasedOnFilter() se llamará para asegurar que las fechas se actualicen
            // con la lógica de la última liquidación o el día actual.
            $this->initializeDatesBasedOnFilter(); // ¡Asegura que las fechas se establezcan correctamente!
            $this->computeStats(); // Recalcula estadísticas para el usuario seleccionado
        }

        $this->dispatch('updateChartData',
            usuarioSeleccionadoId: $this->usuarioSeleccionado->id,
            rolSeleccionado: $this->rolSeleccionado
        );
    }

    public function closeList(): void
    {
        $this->showList = false;
    }
}