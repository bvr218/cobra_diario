<x-filament::page class="p-4">

    <div x-data="{ tab: 'ruta', init() {
        // Opcional: para permitir enlazar directamente a la pestaña de pago
        if (window.location.hash === '#pago') {
            this.tab = 'pago';
            $nextTick(() => {
                window.scrollTo(0, document.body.scrollHeight);
            });
        }
    } }" class="max-w-5xl mx-auto">
        {{-- HEADERS --}}
        <div class="flex border-b border-gray-200 mb-4">
            <button
                @click="tab = 'ruta'"
                :class="tab==='ruta'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-gray-600 hover:text-gray-800'"
                class="px-4 py-2 border-b-2 font-medium transition">
                Ordenar Ruta
            </button>
            <button
                @click="tab = 'pago'; $nextTick(() => window.scrollTo(0, document.body.scrollHeight));"
                :class="tab==='pago'
                    ? 'border-primary text-primary'
                    : 'border-transparent text-gray-600 hover:text-gray-800'"
                class="px-4 py-2 border-b-2 font-medium transition">
                Generar Pago
            </button>
        </div>

        {{-- PESTAÑA 1 --}}
        <div x-show="tab==='ruta'">
            <livewire:filament.ruta-clientes />
        </div>

        {{-- PESTAÑA 2 --}}
        <div x-show="tab==='pago'">
            <livewire:filament.generar-pago />
        </div>
    </div>
</x-filament::page>