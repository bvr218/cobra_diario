@props([
    'livewire' => null,
])

<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ __('filament-panels::layout.direction') ?? 'ltr' }}"
    @class([
        'fi min-h-screen',
        'dark' => filament()->hasDarkModeForced(),
    ])
>
    <head>
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::HEAD_START, scopes: $livewire->getRenderHookScopes()) }}

        <meta charset="utf-8" />
        <meta name="csrf-token" content="{{ csrf_token() }}" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />

        @if ($favicon = filament()->getFavicon())
            <link rel="icon" href="{{ $favicon }}" />
        @endif

        @php
            $title = trim(strip_tags(($livewire ?? null)?->getTitle() ?? ''));
            $brandName = trim(strip_tags(filament()->getBrandName()));
        @endphp

        <title>
            {{ filled($title) ? "{$title} - " : null }} {{ $brandName }}
        </title>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::STYLES_BEFORE, scopes: $livewire->getRenderHookScopes()) }}

        <style>
            [x-cloak=''],
            [x-cloak='x-cloak'],
            [x-cloak='1'] {
                display: none !important;
            }

            @media (max-width: 1023px) {
                [x-cloak='-lg'] {
                    display: none !important;
                }
            }

            @media (min-width: 1024px) {
                [x-cloak='lg'] {
                    display: none !important;
                }
            }
        </style>

        @filamentStyles

        {{ filament()->getTheme()->getHtml() }}
        {{ filament()->getFontHtml() }}

        <style>
            :root {
                --font-family: '{!! filament()->getFontFamily() !!}';
                --sidebar-width: {{ filament()->getSidebarWidth() }};
                --collapsed-sidebar-width: {{ filament()->getCollapsedSidebarWidth() }};
                --default-theme-mode: {{ filament()->getDefaultThemeMode()->value }};
            }
        </style>

        @stack('styles')

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::STYLES_AFTER, scopes: $livewire->getRenderHookScopes()) }}

        @if (! filament()->hasDarkMode())
            <script>
                localStorage.setItem('theme', 'light')
            </script>
        @elseif (filament()->hasDarkModeForced())
            <script>
                localStorage.setItem('theme', 'dark')
            </script>
        @else
            <script>
                const loadDarkMode = () => {
                    window.theme = localStorage.getItem('theme') ?? @js(filament()->getDefaultThemeMode()->value)

                    if (
                        window.theme === 'dark' ||
                        (window.theme === 'system' &&
                            window.matchMedia('(prefers-color-scheme: dark)')
                                .matches)
                    ) {
                        document.documentElement.classList.add('dark')
                    }
                }

                loadDarkMode()

                document.addEventListener('livewire:navigated', loadDarkMode)
            </script>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::HEAD_END, scopes: $livewire->getRenderHookScopes()) }}
    </head>

    <body
        {{ $attributes
                ->merge(($livewire ?? null)?->getExtraBodyAttributes() ?? [], escape: false)
                ->class([
                    'fi-body',
                    'fi-panel-' . filament()->getId(),
                    'min-h-screen bg-gray-50 font-normal text-gray-950 antialiased dark:bg-gray-950 dark:text-white',
                ]) }}
    >
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::BODY_START, scopes: $livewire->getRenderHookScopes()) }}

        {{ $slot }}

        @livewire(Filament\Livewire\Notifications::class)

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SCRIPTS_BEFORE, scopes: $livewire->getRenderHookScopes()) }}

        @filamentScripts(withCore: true)

        @if (filament()->hasBroadcasting() && config('filament.broadcasting.echo'))
            <script data-navigate-once>
                window.Echo = new window.EchoFactory(@js(config('filament.broadcasting.echo')))

                window.dispatchEvent(new CustomEvent('EchoLoaded'))
            </script>
        @endif

        @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
            <script>
                loadDarkMode()
            </script>
        @endif

        @stack('scripts')

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SCRIPTS_AFTER, scopes: $livewire->getRenderHookScopes()) }}

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::BODY_END, scopes: $livewire->getRenderHookScopes()) }}

        <script src="https://maps.googleapis.com/maps/api/js?key={{ env('MAPS_KEY') }}&mapId={{env('MAPS_ID')}}&callback=initMap&libraries=marker&v=beta&loading=async" defer async></script>
        <script>
            // Función global vacía o manejador inicial si es necesario fuera del modal
            function initMap() {
                console.log("Google Maps API loaded.");
            }
            
        </script>
        <script>
            window.mapView = function(config) {

                console.log(config);
                return {
                    map: null,
                    marker: null,
                    lat: -34.6037, // Default Lat
                    lng: -58.3816, // Default Lng
                    mapId: "{{env('MAPS_ID')}}",
                    zoom: 6,
                    inputId: config.inputId,
                    initial: config.initial,
                    userList: config.userlist,

                    initMap() {
                        // Espera un breve instante para asegurar que el modal esté visible
                        // y el contenedor tenga dimensiones. A veces necesario.
                        setTimeout(() => {
                            this.parseInitialCoordinates();

                            try {
                                // Asegurarse que google.maps está cargado
                                if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                                    console.error('Google Maps API not loaded.');
                                    // Intentar cargarla dinámicamente o esperar si se usa callback
                                    // O simplemente mostrar un error al usuario
                                    this.$refs.mapContainer.innerText = 'Error: Google Maps API no pudo cargarse.';
                                    return;
                                }

                                this.map = new google.maps.Map(this.$refs.mapContainer, {
                                    center: { lat: this.lat, lng: this.lng },
                                    zoom: this.zoom,
                                    mapId: this.mapId
                                });
                                

                                this.marker = new google.maps.Marker({
                                    map: this.map,
                                    position: { lat: this.lat, lng: this.lng }
                                });

                                // Event listener para clicks en el mapa
                                this.map.addListener('click', (event) => {
                                    this.updateCoordinates(event.latLng);
                                });

                                // Si hay coordenadas iniciales, centrar y marcar
                                if (this.lat !== -34.6037 || this.lng !== -58.3816) { // Si no son los defaults
                                    const initialPosition = { lat: this.lat, lng: this.lng };
                                    this.map.setCenter(initialPosition);
                                    this.marker.position = initialPosition;
                                }

                                this.printUserCoordinates();

                            } catch (e) {
                                console.error("Error initializing Google Map:", e);
                                this.$refs.mapContainer.innerText = 'Error al inicializar el mapa.';
                            }
                        }, 150); // 150ms delay, ajustar si es necesario
                    },

                    parseInitialCoordinates() {
                        const coords = config.initial; // Lee del input al iniciar
                        if (coords) {
                            const parts = coords.split(',');
                            if (parts.length === 2) {
                                const parsedLat = parseFloat(parts[0].trim());
                                const parsedLng = parseFloat(parts[1].trim());
                                if (!isNaN(parsedLat) && !isNaN(parsedLng)) {
                                    this.lat = parsedLat;
                                    this.lng = parsedLng;
                                    // Puedes ajustar el zoom si lo deseas
                                    this.zoom = 6;
                                }
                            }
                        }
                        console.log('Initial coords parsed:', this.lat, this.lng);
                    },

                    parseCoordinates(coords) {
                        let lat = "";
                        let lng = "";
                        let zoom = "";
                        if (coords) {
                            const parts = coords.split(',');
                            if (parts.length === 2) {
                                const parsedLat = parseFloat(parts[0].trim());
                                const parsedLng = parseFloat(parts[1].trim());
                                if (!isNaN(parsedLat) && !isNaN(parsedLng)) {
                                    lat = parsedLat;
                                    lng = parsedLng;
                                    // Puedes ajustar el zoom si lo deseas
                                    zoom = 6;
                                }
                            }
                        }
                        return {lat,lng,zoom};
                        
                    },

                    updateCoordinates(latLng) {
                        this.lat = latLng.lat();
                        this.lng = latLng.lng();
                        const newPosition = { lat: this.lat, lng: this.lng };

                        // Actualizar posición del marcador
                        if (this.marker) {
                            this.marker.setPosition(newPosition);
                        } else { // Crear marcador si no existe (poco probable aquí)
                            this.marker = new google.maps.Marker({
                                map: this.map,
                                position: newPosition
                            });
                        }

                        // Formatear para el input (ej: "lat, lng")
                        const formattedCoords = this.lat.toFixed(6)+","+this.lng.toFixed(6);

                        // Actualizar el campo de texto en el modal
                        const inputElement = document.getElementById(this.inputId);
                        if (inputElement) {
                            inputElement.value = formattedCoords;
                            // Disparar evento 'input' para que Filament/Livewire detecten el cambio
                            inputElement.dispatchEvent(new Event('input'));
                        } else {
                            // console.warn('Input element with ID not found.');
                        }
                    },

                    printUserCoordinates(){
                        const users = JSON.parse(this.userList);
                        for (const user of users) {


                            const infoWindow = new google.maps.InfoWindow({
                                content: `
                                <div style="text-align: center; padding: 10px; border-radius: 8px; background-color: #bbbbbb; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); width: 200px;">
                                    <h3 style="color: #333; font-size: 18px; margin-bottom: 10px;"><b>${user.label}</b></h3>
                                    <img src="${user.image}" alt="Imagen" width="100" style="margin:auto;border-radius: 50%; box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);" />
                                </div>`,
                            });

                            salida = this.parseCoordinates(user.coor);
                            let marker = new google.maps.Marker({
                                map: this.map,
                                position: { lat: salida.lat, lng: salida.lng },
                                title: user.label,
                                icon: {
                                    url: user.image,
                                    scaledSize: new google.maps.Size(32, 32),
                                    anchor: new google.maps.Point(16, 32),
                                },
                            });
                            marker.addListener('click', function() {
                                infoWindow.open(this.map, marker);
                            });
                        }
                    },
               

                    getInputValue() {
                        const inputElement = document.getElementById(this.inputId);
                        return inputElement ? inputElement.value : null;
                    }
                }
            }
        </script>
    </body>
</html>
