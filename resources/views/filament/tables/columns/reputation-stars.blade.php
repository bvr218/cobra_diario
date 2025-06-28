@php
    // $getRecord() es una función helper disponible en las vistas de columna
    // que te da acceso al modelo Eloquent de la fila actual.
    $record = $getRecord();

    // Accede a tus atributos (o accesor) del modelo
    // Asegúrate de que estos métodos/propiedades existen en tu modelo Cliente
    $reputacion = $record->reputacion; // O $record->getReputacionAttribute() si es un accesor
    $pagosAdelantados = $record->pagos_adelantados;
    $pagosAtrasadosTotales = $record->pagos_atrasados_totales;

    $maxStars = 5; // Número total de estrellas a mostrar
@endphp

<div style="display: flex; flex-direction: column; align-items: start;">
    {{-- Sección de Estrellas --}}
    <div style="display: flex; align-items: center; margin-bottom: 4px;">
        @for ($i = 1; $i <= $maxStars; $i++)
            @if ($i <= $reputacion)
                {{-- Estrella llena --}}
                <x-heroicon-s-star class="w-5 h-5 text-yellow-400" />
            @else
                {{-- Estrella vacía --}}
                <x-heroicon-o-star class="w-5 h-5 text-gray-300" />
            @endif
        @endfor
        <span style="margin-left: 8px; font-weight: bold; font-size:0.9em;">
            ({{ $reputacion }}/{{ $maxStars }})
        </span>
    </div>

    {{-- Sección de Información Adicional --}}
    <div style="font-size: 0.75rem; color: #555; line-height:1.4;">
        <span style="color: #16a34a; font-weight: 500;">
            Adelantados: {{ $pagosAdelantados }}
        </span><br>
        <span style="color: #dc2626; font-weight: 500;">
            Atrasados: {{ $pagosAtrasadosTotales }}
        </span>
    </div>
</div>