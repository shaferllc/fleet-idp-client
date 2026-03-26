<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LocalEmailLoginMagicLinkNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $magicUrl,
        private readonly int $expiresInMinutes,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('fleet-idp::email_sign_in.mail_magic_subject', ['app' => config('app.name')]))
            ->line(__('fleet-idp::email_sign_in.mail_magic_intro', [
                'app' => config('app.name'),
                'minutes' => $this->expiresInMinutes,
            ]))
            ->action(__('fleet-idp::email_sign_in.mail_magic_action'), $this->magicUrl)
            ->line(__('fleet-idp::email_sign_in.mail_footer_ignore'));
    }
}
