<!-- resources/views/livewire/filament-notification-bell.blade.php -->
<div  wire:poll class="relative ms-3" x-data="{ open: @entangle('showDropdown').live }"> <!-- .live para sincronización más inmediata con Livewire -->
    <button
        type="button"
        class="relative flex items-center justify-center w-10 h-10 text-gray-500 rounded-full hover:bg-gray-500/5 focus:bg-gray-500/5 focus:outline-none dark:text-gray-400 dark:hover:bg-gray-700 dark:focus:bg-gray-700"
        aria-label="Notificaciones"
        wire:click="toggleDropdown" {{-- Dejamos que Livewire maneje el toggle --}}
        x-ref="notificationButton" {{-- Referencia para Alpine si la necesitamos, aunque no en este caso --}}
    >
        <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
        </svg>

        @if ($unreadCount > 0)
            <div style="background-color:red" class="absolute inline-flex items-center justify-center w-7 h-7 text-xs font-bold text-white border-2 border-white rounded-full -top-1 -end-1 dark:border-gray-900">
                {{ $unreadCount }}
            </div>
        @endif
    </button>

    <div
        x-show="open"
        x-on:click.away="if (open) { open = false; $wire.set('showDropdown', false); }" 
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-1"
        class="absolute z-50 -end-2 sm:end-0 w-96 origin-top-right bg-white border border-gray-200 divide-y divide-gray-100 rounded-md shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-gray-800 dark:border-gray-700 dark:divide-gray-700" {{-- CAMBIO: w-80 a w-96 --}}
        role="menu"
        aria-orientation="vertical"
        aria-labelledby="user-menu-button"
    
        tabindex="-1"
        style="display: none;width: 350px; margin-right:1.5rem;" 
    >
        <div class="px-4 py-3">
            <p class="text-sm font-medium text-gray-900 dark:text-white">
                Notificaciones
            </p>
        </div>

        <div class="py-1 max-h-96 overflow-y-auto">
            @if ($unreadNotifications->isNotEmpty()) 
                @foreach ($unreadNotifications as $notification)
                    <div
                        class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-700 cursor-pointer"
                        wire:click="markAsRead('{{ $notification->id }}')"
                        role="menuitem"
                        tabindex="-1"
                        id="notification-{{ $notification->id }}"
                    >
                        <p class="font-semibold truncate">{{ $notification->data['title'] ?? 'Notificación' }}</p>
                        <p class="text-gray-500 dark:text-gray-400 truncate">{{ $notification->data['message'] ?? 'Sin mensaje.' }}</p>
                        <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                            {{ $notification->created_at->diffForHumans() }}
                        </p>
                        @if(isset($notification->data['url']) && $notification->data['url'])
                            <a href="{{ url($notification->data['url']) }}" class="text-primary-600 hover:text-primary-500 text-xs mt-1 block" >
                                Ver detalle
                            </a>
                        @endif
                    </div>
                @endforeach
            @else
                <div class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                    No tienes notificaciones nuevas.
                </div>
            @endif
        </div>

        @if ($unreadCount > 0)
            <div class="px-4 py-3">
                <button
                    wire:click="markAllAsRead"
                    type="button"
                    class="w-full px-3 py-2 text-sm font-medium text-center text-white bg-primary-600 rounded-lg hover:bg-primary-700 focus:ring-4 focus:outline-none focus:ring-primary-300 dark:bg-primary-600 dark:hover:bg-primary-700 dark:focus:ring-primary-800"
                >
                    Marcar todas como leídas
                </button>
            </div>
        @endif
         <!-- Opcional: Enlace a una página de "Todas las notificaciones" -->
        <div class="block px-4 py-3 text-center">
            <a href="#" class="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-500 dark:hover:text-primary-400">
                Ver todas las notificaciones
            </a>
        </div>
    </div>
</div>