<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="application-name" content="{{ config('app.name') }}">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name') }}</title>

        @livewireStyles

        @filamentStyles

        @vite('resources/css/app.css')

        <style>
            [x-cloak] {
                display: none !important;
            }
        </style>

        {{-- <script src="https://cdn.jsdelivr.net/npm/chart.js"></script> --}}
        {{-- Puedes cargar Chart.js aquí directamente también si lo prefieres para evitar el loadChartJs --}}
        {{-- Pero es mejor dejarlo en el componente Livewire con @script o @push para que se cargue solo cuando sea necesario --}}

    </head>

    <body class="antialiased">
        @if(session('system_closed_message'))
            <div class="bg-red-500 text-white p-4 fixed top-0 left-0 w-full z-50 text-center">
                {{ session('system_closed_message') }}
            </div>
        @endif

        {{ $slot }}

        @filamentScripts

        @livewireScripts

        @vite('resources/js/app.js')

        <script defer src="//unpkg.com/alpinejs"></script>

        {{-- ¡¡¡AÑADE ESTA LÍNEA AQUÍ!!! --}}
        @stack('scripts')

    </body>
</html>