<?php

declare(strict_types=1);

namespace App\Modules\Identity\Notifications;

use App\Modules\Identity\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** L'e-mail qui porte le lien de réinitialisation du mot de passe d'un compte du personnel. */
final class ResetPasswordNotification extends Notification
{
    public function __construct(#[\SensitiveParameter] private readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire');

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe')
            ->greeting('Bonjour '.$notifiable->name.',')
            ->line('Vous recevez cet e-mail car une réinitialisation du mot de passe a été demandée pour votre compte.')
            ->action('Choisir un nouveau mot de passe', $this->url($notifiable))
            ->line("Ce lien expire dans {$minutes} minutes et ne peut servir qu'une fois.")
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.")
            ->salutation('Marché de Yama');
    }

    /** La page de réinitialisation du backoffice, avec le jeton et l'e-mail (encodés). */
    public function url(User $user): string
    {
        return rtrim((string) config('app.backoffice_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $user->email,
        ]);
    }
}
