
<div
    x-data="mapView({
        inputId: '{{ $coordinatesInputId }}', // Pasa el ID del input a Alpine
        initial: '{{ $initialCoordinates }}',  // Pasa las coords iniciales
        userlist: '{{ $userList }}',  // Pasa las coords de los usuarios
    })"
    x-init="initMap()"
    wire:ignore 
>
    {{-- El contenedor del mapa --}}
    <div x-ref="mapContainer" style="height: 400px; width: 100%;"></div>
{{-- Muestra Lat/Lng (Opcional, para depuración o UI) --}}
{{-- <p class="text-sm mt-2">Lat: <span x-text="lat"></span>, Lng: <span x-text="lng"></span></p> --}}

{{-- Carga de la API de Google Maps (Alternativa si no se carga globalmente) --}}
{{-- @once
<script src="https://maps.googleapis.com/maps/api/js?key={{ env('MAPS_KEY') }}}&mapId={{env('MAPS_ID')}}&libraries=marker&v=beta&loading=async" defer async></script>
@endonce --}}

</div>
