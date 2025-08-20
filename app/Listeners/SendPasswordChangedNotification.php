<?php

namespace App\Listeners;

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Notifications\Messages\MailMessage;

class SendPasswordChangedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(PasswordReset $event): void
    {
        $user = $event->user;
        $user->notify(new class extends \Illuminate\Notifications\Notification {
            public function via($notifiable){ return ['mail']; }
            public function toMail($notifiable){
                return (new MailMessage)
                    ->subject('Tu contraseña ha sido cambiada')
                    ->greeting('Hola,')
                    ->line('Se ha cambiado la contraseña de tu cuenta recientemente.')
                    ->line('Si fuiste tú, no necesitas hacer nada más.')
                    ->line('Si NO fuiste tú, restablece nuevamente la contraseña y contacta al administrador.')
                    ->salutation('Atentamente, Sistema de Credenciales');
            }
        });
    }
}
