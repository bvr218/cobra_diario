<div class="gpmod-route-container">
    <div
        wire:ignore
        x-data
        x-init="
            Sortable.create($refs.tarjetas, {
                animation: 150,
                onEnd: (evt) => {
                    const ordenIds = Array.from($refs.tarjetas.children).map(el => el.dataset.id);
                    @this.call('actualizarOrden', ordenIds);
                    Array.from($refs.tarjetas.children).forEach((tarjetaNode, newIndex) => {
                        const numeroBox = tarjetaNode.querySelector('.gpmod-route-number-box');
                        if (numeroBox) {
                            numeroBox.textContent = newIndex + 1;
                        }
                    });
                }
            })
        "
        class="gpmod-route-wrapper"
        x-ref="tarjetas"
    >
        @if($prestamos->isEmpty())
            <div class="gpmod-no-loans-msg">
                No tiene préstamos asignados.
            </div>
        @else
            @foreach($prestamos as $index => $prestamo)
                <div 
                    @class([
                        'gpmod-route-card',
                        'atraso-nivel-1' => $prestamo->atraso_nivel === 1,
                        'atraso-nivel-2' => $prestamo->atraso_nivel === 2,
                        'atraso-nivel-3' => $prestamo->atraso_nivel === 3,
                    ])
                    data-id="{{ $prestamo->id }}" 
                    wire:key="prestamo-{{ $prestamo->id }}"
                >
                    <div class="gpmod-route-number-box">{{ $index + 1 }}</div>
                    <div class="gpmod-route-card-content">
                        <p class="gpmod-route-client-name">{{ $prestamo->cliente->nombre }}</p>
                        
                        <div class="gpmod-route-client-detail">
                            <span class="gpmod-route-detail-title">CC:</span> {{ $prestamo->cliente->numero_cedula ?? 'N/A' }}
                        </div>
                        
                        <div class="gpmod-route-client-detail">
                            <span class="gpmod-route-detail-title">Deuda Actual:</span> ${{ number_format($prestamo->deuda_actual, 0) }}
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>