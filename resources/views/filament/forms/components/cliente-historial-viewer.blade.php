{{-- resources/views/filament/forms/components/cliente-historial-viewer.blade.php --}}
@props(['cliente'])

<div
    wire:key="cliente-historial-viewer-wrapper-{{ $cliente->id }}"
    wire:poll
    class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-lg"
>
    @livewire(
        \App\Livewire\ClienteHistorialModal::class,
        ['cliente' => $cliente],
        key('historial-modal-cliente-' . $cliente->id . '-' . now()->timestamp . '-' . \Illuminate\Support\Str::random(5))
    )
</div>
