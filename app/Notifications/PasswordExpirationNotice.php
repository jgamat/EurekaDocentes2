<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordExpirationNotice extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public int $daysUntilExpiration
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Aviso de expiración de contraseña')
            ->greeting('Hola '.$notifiable->name.',')
            ->line("Tu contraseña caducará en {$this->daysUntilExpiration} días por políticas de seguridad.")
            ->line('Por favor, ingresa al sistema y actualiza tu contraseña a la brevedad.')
            ->action('Ingresar al sistema', url('/'))
            ->line('Gracias por tu colaboración.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return \Filament\Notifications\Notification::make()
            ->title('Expiración de contraseña')
            ->body("Tu contraseña vencerá en {$this->daysUntilExpiration} días. Por favor, cámbiala.")
            ->warning()
            ->getDatabaseMessage();
    }
}
