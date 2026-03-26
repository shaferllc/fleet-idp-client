<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LocalEmailLoginCodeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $code,
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
            ->subject(__('fleet-idp::email_sign_in.mail_code_subject', ['app' => config('app.name')]))
            ->line(__('fleet-idp::email_sign_in.mail_code_intro', [
                'app' => config('app.name'),
                'minutes' => $this->expiresInMinutes,
            ]))
            ->line(__('fleet-idp::email_sign_in.mail_code_value', ['code' => $this->code]))
            ->line(__('fleet-idp::email_sign_in.mail_footer_ignore'));
    }
}
