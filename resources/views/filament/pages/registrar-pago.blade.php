<x-filament-panels::page>
    {{ $this->form }}

    <div class="mt-4">
        @if ($this->prestamo_id)
            <x-filament::button
                wire:click="guardarAbono"
                wire:loading.attr="disabled"
                color="primary"
            >
                Crear Registro
            </x-filament::button>
        @endif
    </div>
</x-filament-panels::page>
