<?php
// app/Livewire/FilamentNotificationBell.php (o ruta similar)
namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FilamentNotificationBell extends Component
{
    public $unreadNotifications;
    public $unreadCount = 0;
    public bool $showDropdown = false; // Importante tipar como bool

    protected $listeners = ['refreshNotifications' => 'loadUnreadNotifications'];

    public function mount()
    {
        $this->loadUnreadNotifications();
    }

    public function loadUnreadNotifications()
    {
        if (Auth::check()) {
            $this->unreadNotifications = Auth::user()->unreadNotifications()->orderBy('created_at', 'desc')->take(10)->get();
            $this->unreadCount = Auth::user()->unreadNotifications()->count();
        } else {
            $this->unreadNotifications = collect();
            $this->unreadCount = 0;
        }
    }

    public function toggleDropdown()
    {
        $this->showDropdown = !$this->showDropdown;
        if ($this->showDropdown) {
            // Cargar notificaciones solo cuando se abre el dropdown y está vacío o quieres refrescar
            // Podrías añadir una condición para no recargar si ya están cargadas y son recientes.
            $this->loadUnreadNotifications();
        }
    }

    public function markAsRead($notificationId)
    {
        if (Auth::check()) {
            $notification = Auth::user()->notifications()->find($notificationId);
            if ($notification) {
                $notification->markAsRead();
                $this->loadUnreadNotifications(); // Recargar para actualizar contador y lista

                // Si la notificación tiene una URL, puedes decidir si quieres redirigir
                // o simplemente cerrar el dropdown. Aquí solo actualizamos y mantenemos abierto.
                // Si quieres cerrar el dropdown después de marcar como leída:
                // $this->showDropdown = false;
            }
        }
    }

    public function markAllAsRead()
    {
        if (Auth::check()) {
            Auth::user()->unreadNotifications->markAsRead();
            $this->loadUnreadNotifications();
            // $this->showDropdown = false; // Opcional: cerrar después de marcar todas
        }
    }

    public function render()
    {
        $this->loadUnreadNotifications();
        return view('livewire.filament-notification-bell');
    }
}