<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NuevaNotificacion extends Notification
{
    use Queueable;

       use Queueable;

    public string $title;
    public string $message;
    public ?string $url;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $title, string $message, ?string $url = null)
    {
        $this->title = $title;
        $this->message = $message;
        $this->url = $url;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database']; // Importante para guardar en la BBDD
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->message,
            'url' => $this->url,
            // Puedes añadir más datos que necesites
        ];
    }

    // Opcional: toDatabase si quieres una estructura diferente para la BBDD
    // public function toDatabase(object $notifiable): array
    // {
    //     return [
    //         'title' => $this->title,
    //         'message' => $this->message,
    //         'url' => $this->url,
    //     ];
    // }
}
