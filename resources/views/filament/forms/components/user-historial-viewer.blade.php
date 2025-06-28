{{-- resources/views/filament/forms/components/user-historial-viewer.blade.php --}}

<div
    wire:key="user-historial-modal-wrapper-{{ $user }}"
    wire:poll
    id="user-historial-modal-{{ $user }}"
    class="p-4 bg-white dark:bg-gray-800 rounded-xl shadow-lg"
>
    @livewire(
        \App\Livewire\UserHistorialModal::class,
        ['user' => $user],
        key('historial-modal-user-' . $user . '-' . now()->timestamp . '-' . \Illuminate\Support\Str::random(5))
    )
</div> 