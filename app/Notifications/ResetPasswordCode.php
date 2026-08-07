<?php

namespace App\Notifications;

use App\Actions\SendPasswordResetCode;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email carrying a one-time code for resetting a password from the app.
 *
 * Deliberately not queued. The queue runs on the database connection, so a
 * queued code sits untouched until a worker happens to be running — and the
 * one moment a person is certain to be staring at their inbox is the moment
 * after asking for it. The send is one SMTP handoff to Brevo; the request
 * waits for it.
 */
class ResetPasswordCode extends Notification
{
    /**
     * Readable rather than private so tests can assert against the code that
     * was actually sent instead of scraping it back out of the rendered mail.
     */
    public function __construct(public readonly string $code) {}

    /**
     * The channels the notification should be delivered on.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            // The code is in the subject as well as the body so it can be read
            // from a notification banner without opening anything — which is
            // most of what makes a code nicer than a link on a phone.
            ->subject($this->code.' is your '.$appName.' password reset code')
            // Both halves of a multipart message: the designed HTML, and a
            // plain-text alternative for clients that will not render it and
            // for spam filters, which mark HTML-only mail down.
            ->view(['mail.reset-password-code', 'mail.reset-password-code-text'], [
                'code' => $this->code,
                'minutes' => SendPasswordResetCode::EXPIRES_MINUTES,
                'appName' => $appName,
            ]);
    }
}
