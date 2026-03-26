<?php

declare(strict_types=1);

namespace Fleet\IdpClient\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmProfileMagicLinkSignInNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $confirmationUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $app = config('app.name');

        return (new MailMessage)
            ->subject(__('fleet-idp::email_sign_in.profile_confirm_magic_subject', ['app' => $app]))
            ->line(__('fleet-idp::email_sign_in.profile_confirm_magic_intro', ['app' => $app]))
            ->action(
                __('fleet-idp::email_sign_in.profile_confirm_magic_action'),
                $this->confirmationUrl
            )
            ->line(__('fleet-idp::email_sign_in.mail_footer_ignore'));
    }
}
