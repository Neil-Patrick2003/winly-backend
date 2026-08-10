<?php

namespace App\Notifications;

use App\Actions\SendPasswordResetCode;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * The email carrying a one-time code for resetting a password from the app.
 *
 * Queued, so the request that asks for a code returns as soon as the row is
 * written rather than holding the connection open for an SMTP conversation.
 * That handoff is the slowest thing in the endpoint and it is not something
 * the caller can act on: the response says the same words whether or not an
 * account exists, so there is nothing in the mail result worth waiting for.
 *
 * The cost is an operational one, and it is real: `QUEUE_CONNECTION=database`,
 * so nothing is delivered unless a worker is running. Without one the codes
 * pile up in `jobs` and every reset silently fails — see `failed()` for what
 * happens when a send is attempted and does not get through.
 */
class ResetPasswordCode extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * A refused relay is usually a moment's trouble rather than a permanent
     * one, so a code gets three goes before it is given up on.
     */
    public int $tries = 3;

    /**
     * Seconds to wait between those goes.
     *
     * Deliberately short. The code is good for fifteen minutes and the person
     * is watching their inbox, so a backoff measured in minutes would exhaust
     * the window rather than survive it.
     */
    public array $backoff = [10, 30];

    /**
     * Readable rather than private so tests can assert against the code that
     * was actually sent instead of scraping it back out of the rendered mail.
     *
     * The address rides along because `failed()` is handed nothing but the
     * exception — the notifiable is not passed back — and clearing the stored
     * row is the whole point of knowing the send did not work.
     */
    public function __construct(
        public readonly string $code,
        public readonly string $email,
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

    /**
     * Clear the stored code once the send has been given up on.
     *
     * Sending used to happen inside the request, so a failure could be caught
     * where the row had just been written. Off the queue that failure lands
     * here instead, minutes later and in another process — and it still has to
     * clear the row. Left behind, it marks the address as having been written
     * to moments ago, the retry is silently swallowed by the throttle, and the
     * person waits for a second mail that was never going to come either.
     */
    public function failed(Throwable $failure): void
    {
        app(SendPasswordResetCode::class)->discard($this->email, $this->code);
    }
}
