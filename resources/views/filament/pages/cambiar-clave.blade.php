<x-filament::page>
    <form wire:submit.prevent="submit" class="space-y-6 max-w-md">
        {{ $this->form }}
        <x-filament::button type="submit" color="primary">
            Guardar nueva contraseña
        </x-filament::button>
    </form>
</x-filament::page>
