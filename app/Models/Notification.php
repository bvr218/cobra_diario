<?php

namespace App\Models;

// Importa la clase base de notificaciones de Laravel
use Illuminate\Notifications\DatabaseNotification as BaseNotification;

class Notification extends BaseNotification
{
   
    /**
     * Los atributos que deben ser convertidos a tipos nativos.
     * Estos ya están definidos en la clase BaseNotification,
     * pero los incluimos aquí para mayor claridad si quisieras extenderlos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',      // La columna 'data' se convierte a/desde JSON
        'read_at' => 'datetime', // La columna 'read_at' se convierte a instancia de Carbon
        // 'created_at' y 'updated_at' son manejados por defecto.
    ];


    // --- Aquí puedes agregar tus propios métodos, scopes o relaciones personalizadas ---

    /**
     * Ejemplo: Un accesor para obtener el título de la notificación desde la data.
     * Suponiendo que guardas un 'title' en el array 'data'.
     *
     * @return string|null
     */
    public function getTitleAttribute(): ?string
    {
        return $this->data['title'] ?? null;
    }

    /**
     * Ejemplo: Un accesor para obtener el mensaje de la notificación.
     * Suponiendo que guardas un 'message' en el array 'data'.
     *
     * @return string|null
     */
    public function getMessageAttribute(): ?string
    {
        return $this->data['message'] ?? null;
    }

    /**
     * Ejemplo: Un accesor para obtener la URL de la notificación.
     * Suponiendo que guardas una 'url' en el array 'data'.
     *
     * @return string|null
     */
    public function getUrlAttribute(): ?string
    {
        return $this->data['url'] ?? null;
    }

    /**
     * Ejemplo: Un scope para obtener solo notificaciones de un tipo específico.
     * El 'type' es el nombre completo de la clase de la notificación (ej. App\Notifications\NewUserRegistered).
     */
    public function scopeOfType($query, string $notificationClassName)
    {
        return $query->where('type', $notificationClassName);
    }
}