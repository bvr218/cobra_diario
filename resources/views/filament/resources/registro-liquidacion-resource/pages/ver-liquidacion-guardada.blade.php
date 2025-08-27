<x-filament-panels::page>
    {{-- Aquí renderizamos el componente Livewire de solo lectura --}}
    {{-- Le pasamos el ID del registro que la página ya ha cargado --}}
    <livewire:vista-liquidacion-guardada :recordId="$record->id" />
</x-filament-panels::page>