<?php

namespace App\Notifications;

use App\Actions\SendPasswordResetCode;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email carrying a one-time code for resetting a password from the app.
 *
 * Sent inside the request rather than queued. It was queued when the mail went
 * out over SMTP, where the handoff was a multi-step conversation on a socket
 * and easily the slowest thing in the endpoint. Over Brevo's HTTP API it is a
 * single request measured in a few hundred milliseconds, which is not worth
 * deferring — and deferring it cost more than it saved:
 *
 *   - nothing was delivered unless a queue worker happened to be running, and
 *     when none was, codes piled up in `jobs` and every reset failed in silence
 *   - a refusal surfaced minutes later in another process, so clearing the
 *     stored row had to be done from a `failed()` hook rather than at the point
 *     that wrote it
 *
 * Both of those go away here. `SendPasswordResetCode` writes the row, sends,
 * and clears the row itself if the send does not get through.
 *
 * The price is that the request now waits for Brevo, and a refusal reaches the
 * caller as an error rather than being swallowed — see the action for what that
 * means for an endpoint that otherwise answers identically for every address.
 */
class ResetPasswordCode extends Notification
{
    /**
     * Readable rather than private so tests can assert against the code that
     * was actually sent instead of scraping it back out of the rendered mail.
     */
    public function __construct(
        public readonly string $code,
    ) {}

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
