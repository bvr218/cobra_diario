<style>
    .tarjetas-wrapper {
        display: flex;
        flex-direction: column;
        gap: 0.75rem; /* Reducir gap entre tarjetas */
        padding: 1rem;
        margin: 0 auto;
        max-width: 1000px; /* Permitir que las tarjetas sean más anchas si el contenido lo requiere */
    }

    .tarjeta {
        background-color: var(--f-card-background-color, white); /* Adaptativo al tema */
        border-radius: 0.5rem; /* Un poco menos redondeado */
        padding: 0.75rem; /* Reducir padding interno */
        border: 1px solid var(--f-input-border-color, #e5e7eb); /* Adaptativo al tema */
        box-shadow: var(--f-card-box-shadow, 0 1px 3px rgba(0, 0, 0, 0.07)); /* Adaptativo al tema */
        display: flex;
        align-items: flex-start; /* Alinear items al inicio verticalmente */
        cursor: move;
        position: relative;
        font-size: 0.875rem; /* Tamaño de fuente base para la tarjeta */
    }
    .dark .tarjeta {
        background-color: var(--f-card-background-color-dark, #1f2937); /* Adaptativo al tema oscuro */
        border-color: var(--f-input-border-color-dark, #4b5563); /* Adaptativo al tema oscuro */
        box-shadow: var(--f-card-box-shadow-dark, 0 1px 3px rgba(0, 0, 0, 0.3)); /* Adaptativo al tema oscuro */
    }

    .tarjeta-contenido { /* Nueva clase para el div que envuelve nombre, cedula, deuda */
        padding-left: 2.5rem; /* Espacio para el numero-box (ancho de numero-box 2rem + 0.5rem de espacio) */
        display: flex;
        flex-direction: column;
        flex-grow: 1; /* Permite que este contenedor crezca para ocupar el espacio disponible */
    }

    .numero-box {
        position: absolute;
        left: 0.75rem; /* Alineado con el padding de la tarjeta */
        top: 0.75rem;  /* Alineado con el padding de la tarjeta */
        width: 2rem;
        height: 2rem;
        display: flex;
        justify-content: center;
        align-items: center;
        background-color: var(--f-numero-box-bg, white); /* Usamos fallback directo, podría ser --f-button-secondary-background-color */
        border-radius: 9999px;
        font-weight: bold;
        font-size: 0.75rem;
        color: var(--f-numero-box-text, #4b5563);
        box-shadow: var(--f-numero-box-shadow, 0 1px 2px rgba(0, 0, 0, 0.1));
    }
    .dark .numero-box {
        background-color: var(--f-numero-box-bg-dark, #374151);
        color: var(--f-numero-box-text-dark, #e5e7eb);
        box-shadow: var(--f-numero-box-shadow-dark, 0 1px 2px rgba(0, 0, 0, 0.3));
    }

    /* .foto-cliente ya no se usa */

    .info-cliente { /* Nombre del cliente */
        font-weight: bold; /* Solo el nombre en bold */
        color: var(--f-text-color, #1f2937); /* Usando var general de texto con fallback específico */
        font-size: 0.95rem; /* Un poco más grande que el texto base de la tarjeta */
        margin-bottom: 0.2rem; /* Reducir espacio debajo del nombre */
        line-height: 1.2;
    }
    .dark .info-cliente {
        color: var(--f-text-color-dark, #e5e7eb);
    }

    .detalle-cliente, /* Contenedor de Cédula */
    .detalle-prestamo { /* Contenedor de Deuda Actual */
        color: var(--f-text-color-secondary, #4A5568); /* Usando var para texto secundario con fallback */
        font-size: 0.8rem; /* Tamaño de fuente más pequeño para detalles */
        margin-top: 0.05rem; /* Mínimo espacio arriba */
        line-height: 1.2; /* Espaciado entre líneas ajustado */
    }
    .dark .detalle-cliente,
    .dark .detalle-prestamo {
        color: var(--f-text-color-secondary-dark, #9ca3af);
    }

    .titulo-detalle { /* Para "Cédula:" y "Deuda Actual:" */
        font-weight: normal; /* No bold */
        color: inherit; /* Hereda color de .detalle-cliente/.detalle-prestamo */
        margin-right: 0.3em; /* Espacio después del título (ej. Cédula:[espacio]123) */
    }
    /* No se necesita .dark .titulo-detalle si hereda correctamente */

    .mensaje-no-prestamos {
        font-size: 1rem;
        color: var(--f-text-color-muted, #6b7280); /* Usando var para texto muted con fallback */
        text-align: center;
        padding: 1rem;
    }
    .dark .mensaje-no-prestamos {
        color: var(--f-text-color-muted-dark, #a0aec0);
    }
</style>


<div
    wire:ignore
    x-data
    x-init="
        Sortable.create($refs.tarjetas, {
            animation: 150,
            onEnd: (evt) => { // evt puede contener información útil del evento de arrastre
                const ordenIds = Array.from($refs.tarjetas.children).map(el => el.dataset.id);
                @this.call('actualizarOrden', ordenIds);

                // Actualización visual inmediata de los números de posición
                Array.from($refs.tarjetas.children).forEach((tarjetaNode, newIndex) => {
                    const numeroBox = tarjetaNode.querySelector('.numero-box');
                    if (numeroBox) {
                        numeroBox.textContent = newIndex + 1;
                    }
                });
            }
        })
    "
    class="tarjetas-wrapper"
    x-ref="tarjetas"
>
    @if($prestamos->isEmpty())
        <div class="mensaje-no-prestamos">
            No tiene préstamos asignados.
        </div>
    @else
        @foreach($prestamos as $index => $prestamo)
            <div class="tarjeta" data-id="{{ $prestamo->id }}" wire:key="prestamo-{{ $prestamo->id }}">
                <div class="numero-box">{{ $index + 1 }}</div>
                <div class="tarjeta-contenido">
                    <p class="info-cliente">{{ $prestamo->cliente->nombre }}</p>
                    
                    <div class="detalle-cliente">
                        <span class="titulo-detalle">CC:</span> {{ $prestamo->cliente->numero_cedula ?? 'N/A' }}
                    </div>
                    
                    <div class="detalle-prestamo">
                        <span class="titulo-detalle">Deuda Actual:</span> ${{ number_format($prestamo->deuda_actual, 0) }}<br>
                    </div>
                </div>
            </div>
        @endforeach
    @endif
</div>