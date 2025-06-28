{{-- resources/views/livewire/adjust-money-modal.blade.php --}}

<div> {{-- Contenedor raíz único --}}
    @if ($showAdjustMoneyModal)
        {{-- Fondo del modal (backdrop) --}}
        <div class="fixed inset-0 z-40 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" wire:click="closeModal"></div>

        {{-- Contenedor del modal --}}
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0">
            <div
                class="relative w-full max-w-lg overflow-hidden rounded-lg bg-white shadow-xl transition-all dark:bg-gray-800 sm:my-8"
                role="dialog" aria-modal="true" aria-labelledby="modal-headline"
                @click.away="$wire.closeModal()" {{-- Cierra si se hace clic fuera (opcional) --}}
            >
                {{-- Encabezado del Modal --}}
                <div class="border-b border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:px-6">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white" id="modal-headline">
                            Ajustar Dinero Base
                        </h3>
                        <button
                            type="button"
                            wire:click="closeModal"
                            class="rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-300"
                        >
                            <span class="sr-only">Cerrar</span>
                            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Cuerpo del Modal (Formulario) --}}
                <form wire:submit.prevent="adjustDineroBase">
                    <div class="bg-white px-4 pb-4 pt-5 dark:bg-gray-800 sm:p-6 sm:pb-4">
                        <div class="space-y-6">
                            @if ($usuarioSeleccionadoName)
                                <p class="text-sm text-gray-600 dark:text-gray-400">Ajustando dinero base para: <span class="font-bold text-primary-600 dark:text-primary-400">{{ $usuarioSeleccionadoName }}</span></p>
                            @endif

                            <div>
                                <label for="amountToAdjust" class="block text-sm font-medium text-gray-700 dark:text-gray-200">Monto a Ajustar</label>
                                <input type="number" step="0.01" id="amountToAdjust" wire:model.defer="amountToAdjust" required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400 sm:text-sm">
                                @error('amountToAdjust') <span class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Pie del Modal (Botones) --}}
                    <div class="custom-modal-footer">
                        <button type="submit" class="btn-ajustar">
                            Ajustar
                        </button>
                        <button type="button" wire:click="closeModal" class="btn-cancelar">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>