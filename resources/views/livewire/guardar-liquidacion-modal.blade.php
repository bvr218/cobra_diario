<div>
    @if($showModal)
        <div class="modal-save-liquidation-backdrop" aria-hidden="true" wire:click="closeModal"></div>

        <div class="modal-save-liquidation-container">
            <div
                class="modal-save-liquidation-content"
                role="dialog"
                aria-modal="true"
                aria-labelledby="modal-headline-guardar-liquidacion"
                @click.away="$wire.closeModal()"
            >
                {{-- Encabezado del Modal --}}
                <div class="modal-save-liquidation-header">
                    <h3 id="modal-headline-guardar-liquidacion" class="modal-save-liquidation-title">
                        Guardar Liquidación
                    </h3>
                    <button
                        type="button"
                        wire:click="closeModal"
                        class="modal-save-liquidation-close-button"
                    >
                        <span class="sr-only">Cerrar</span>
                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Cuerpo del Modal (Formulario) --}}
                <div class="modal-save-liquidation-body">
                    <div class="mb-4">
                        <label for="liquidacionNombre" class="modal-save-liquidation-label">
                            Nombre de la liquidación
                        </label>
                        <input
                            type="text"
                            id="liquidacionNombre"
                            wire:model.live="liquidacionNombre"
                            class="modal-save-liquidation-input"
                            placeholder="Cualquier nombre..."
                            maxlength="50"
                        >
                        @error('liquidacionNombre')
                            <span class="modal-save-liquidation-error">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                {{-- Pie del Modal (Botones) --}}
                <div class="modal-save-liquidation-footer">
                    <button
                        wire:click="closeModal"
                        type="button"
                        class="modal-button-cancel" {{-- Reutilizando la clase existente --}}
                    >
                        Cancelar
                    </button>
                    <button
                        wire:click="guardarLiquidacion"
                        type="button"
                        class="save-liquidation-button" {{-- Reutilizando tu clase existente --}}
                    >
                        Guardar
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>