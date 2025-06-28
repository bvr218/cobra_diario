<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 mt-4 items-start">
    @foreach ($datos as $campo => $valor)
        @php
            $campoLower = strtolower($campo);
            $camposImagen = ['foto_cliente', 'foto', 'imagen', 'avatar', 'perfil', 'foto_perfil'];
            $esRutaImagen = is_string($valor) && in_array($campoLower, $camposImagen) && $valor !== '';
            $esUrlImagen = is_string($valor) && filter_var($valor, FILTER_VALIDATE_URL) && preg_match('/\.(jpeg|jpg|png|gif|webp|svg)$/i', $valor);
            $esGaleria = is_array($valor) && in_array($campoLower, ['galeria', 'galería']);
            $esArray = is_array($valor);

            if (is_string($valor) && str_starts_with($valor, '[')) {
                $jsonDecoded = json_decode($valor, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $valor = $jsonDecoded;
                    $esGaleria = $campoLower === 'galeria';
                    $esArray = true;
                }
            }

            // Mostrar nombre en vez del ID para cliente_id, agente_asignado, user_id y registrado_id
            if (in_array($campoLower, ['cliente_id', 'agente_asignado', 'user_id', 'registrado_id']) && is_numeric($valor)) {
                if ($campoLower === 'cliente_id') {
                    $modelo = \App\Models\Cliente::find($valor);
                    $valor = $modelo?->nombre ?? 'ID: ' . $valor;
                } elseif ($campoLower === 'agente_asignado') {
                    $modelo = \App\Models\User::find($valor);
                    $valor = $modelo?->name ?? 'ID: ' . $valor;
                } elseif ($campoLower === 'user_id') {
                    $modelo = \App\Models\User::find($valor);
                    $valor = $modelo?->name ?? 'ID: ' . $valor;
                } elseif ($campoLower === 'registrado_id') {
                    $modelo = \App\Models\User::find($valor);
                    $valor = $modelo?->name ?? 'ID: ' . $valor;
                }
            }

            // Mostrar Sí / No para valores 0 o 1 (booleanos)
            if (in_array($campoLower, ['activo', 'autorizado', 'es_vip', 'es_modelo', 'flag'])) {
                if ($valor == 1) {
                    $valor = 'Sí';
                } elseif ($valor == 0) {
                    $valor = 'No';
                }
            }
        @endphp

        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg shadow-sm flex flex-col h-fit">
            <div class="text-sm text-gray-600 dark:text-gray-400 font-semibold mb-2">
                {{ ucwords(str_replace('_', ' ', $campo)) }}
            </div>

            <div>
                @if ($esRutaImagen)
                    <img src="{{ asset('storage/' . $valor) }}" alt="{{ $campo }}" class="w-full h-48 object-cover rounded-md shadow-md" loading="lazy" />
                @elseif ($esUrlImagen)
                    <img src="{{ $valor }}" alt="{{ $campo }}" class="w-full h-48 object-cover rounded-md shadow-md" loading="lazy" />
                @elseif ($esGaleria && count($valor))
                    <div class="grid grid-cols-2 gap-2">
                        @foreach ($valor as $img)
                            @php $imgPath = str_replace(['\\', '"'], '', $img); @endphp
                            <img src="{{ asset('storage/' . $imgPath) }}"
                                 alt="Imagen galería"
                                 class="w-full h-28 object-cover rounded-md shadow border"
                                 loading="lazy" />
                        @endforeach
                    </div>
                @elseif ($esArray)
                    @if (count($valor) === 0)
                        <p class="text-gray-400 dark:text-gray-500 italic">Sin datos</p>
                    @else
                        <ul class="list-disc pl-4 text-gray-800 dark:text-gray-200 text-sm space-y-1">
                            @foreach ($valor as $item)
                                <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                    @endif
                @else
                    <p class="text-gray-800 dark:text-gray-100 text-base break-words">
                        {{ $valor ?? '-' }}
                    </p>
                @endif
            </div>
        </div>
    @endforeach
</div>