@props(['usuarioSeleccionado', 'filtrarPorFecha'])

@if ($usuarioSeleccionado)
    <div class="delete-button-wrapper flex gap-4 justify-center mt-6">
        {{-- Botón para Liquidar Seguros --}}
        <x-filament::button
            color="danger"
            class="delete-commissions-button"
            icon="heroicon-o-trash"
            x-on:click="$wire.call('deleteComisiones')" 
        >
            Liquidar Seguros
        </x-filament::button>

        {{-- Nuevo botón para Ajustar Dinero Base --}}
        <x-filament::button
            icon="heroicon-o-currency-dollar"
            color="primary"
            wire:click="openAdjustMoneyModal"
        >
            Ajustar Dinero Base
        </x-filament::button>
    </div>
@endif