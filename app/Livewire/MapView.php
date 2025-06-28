<?php

namespace App\Livewire;

use Livewire\Component;

class MapView extends Component
{
    public string $coordinatesInputId = '';
    public string $userList = "";
    // Propiedad para recibir las coordenadas iniciales (opcional)
    public ?string $initialCoordinates = null;


    public function render()
    {
        return view('livewire.map-view');
    }
}
