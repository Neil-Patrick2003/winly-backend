<?php

namespace App\Support;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

/**
 * Hands outgoing mail to Brevo over its HTTP API instead of its SMTP relay.
 *
 * Same account and the same verified sender as the relay — only the way the
 * message gets there changes. What that buys is a send that behaves like every
 * other outbound call in the app: one request, one status code, one JSON body
 * saying what happened. SMTP is a multi-step conversation held over a raw
 * socket on port 587, and the ways it fails are the reason this exists —
 * hosts block the port outbound, the greeting stalls behind a slow reverse-DNS
 * lookup, and a relay that will not take the message says so several round
 * trips in. Every one of those surfaces as a socket timeout with nothing in it
 * to act on, which is a bad way to lose a password reset code.
 *
 * Errors here are thrown as `TransportException`, the same type SMTP failures
 * raise, so nothing upstream has to know which transport was in use — a queued
 * notification retries on this exactly as it did before.
 *
 * @see https://developers.brevo.com/reference/sendtransacemail
 */
class BrevoApiTransport extends AbstractTransport
{
    /**
     * Brevo's transactional send endpoint.
     *
     * Note the path: `smtp/email` is what the HTTP API calls itself, and is
     * not the relay. Nothing on this class opens a socket to port 587.
     */
    private const ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    /**
     * @param  PendingRequest  $client  Pre-authenticated with the account's API key.
     */
    public function __construct(private readonly PendingRequest $client)
    {
        parent::__construct();
    }

    /**
     * Post one message to Brevo.
     */
    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        /*
         * Anything Laravel sends is a structured `Message`. A bare
         * `RawMessage` — pre-rendered MIME, handed straight to a relay — has
         * no addressable parts to read a JSON body out of, so it is refused
         * here rather than half-converted into a send that drops its content.
         */
        if (! $original instanceof Message) {
            throw new TransportException(
                'The Brevo API transport can only send a structured message, not raw MIME.'
            );
        }

        $email = MessageConverter::toEmail($original);

        try {
            $response = $this->client->post(self::ENDPOINT, $this->payload($email, $message->getEnvelope()));
        } catch (ConnectionException $failure) {
            // Never reached the API at all — DNS, TLS, or the timeout. Worth
            // separating from a refusal below, because a caller reading the log
            // needs to know whether Brevo formed an opinion about this message.
            throw new TransportException(
                'Could not reach the Brevo API: '.$failure->getMessage(), 0, $failure
            );
        }

        if ($response->failed()) {
            throw new TransportException($this->describe($response), $response->status());
        }

        /*
         * Brevo answers a single-recipient send with `messageId` and a
         * multi-recipient one with `messageIds`. Recording it is what makes a
         * complaint traceable to a send in their dashboard, so take whichever
         * shape came back and take the first when it is the list.
         */
        $id = $response->json('messageId') ?? $response->json('messageIds.0');

        if (is_string($id)) {
            $message->setMessageId($id);
        }
    }

    /**
     * The request body Brevo expects.
     *
     * @return array<string, mixed>
     */
    private function payload(Email $email, Envelope $envelope): array
    {
        /*
         * The envelope sender rather than the header From. They are normally
         * the same address; where they differ the envelope is the one the
         * message is actually being sent as, and Brevo checks it against the
         * senders verified on the account.
         */
        $payload = [
            'sender' => $this->address($envelope->getSender()),
            'to' => $this->addresses($this->recipients($email, $envelope)),
            'subject' => $email->getSubject() ?? '',
        ];

        // Both halves of the multipart message go over as their own fields.
        // Omitted rather than sent empty: Brevo rejects a blank `htmlContent`.
        if (($html = $email->getHtmlBody()) !== null) {
            $payload['htmlContent'] = $this->body($html);
        }

        if (($text = $email->getTextBody()) !== null) {
            $payload['textContent'] = $this->body($text);
        }

        if ($cc = $email->getCc()) {
            $payload['cc'] = $this->addresses($cc);
        }

        if ($bcc = $email->getBcc()) {
            $payload['bcc'] = $this->addresses($bcc);
        }

        // The API takes a single reply-to, not a list, so anything past the
        // first is dropped — as it is by Symfony's own Brevo transport.
        if ($replyTo = $email->getReplyTo()) {
            $payload['replyTo'] = $this->address($replyTo[0]);
        }

        if ($attachments = $this->attachments($email)) {
            $payload['attachment'] = $attachments;
        }

        return $payload;
    }

    /**
     * Who the message is addressed to, as distinct from everyone it is going to.
     *
     * The envelope holds every recipient — To, Cc and Bcc flattened into one
     * list, because that is what a relay is told. Sending that whole list as
     * `to` would publish the blind copies in the visible header of the
     * delivered mail, so the ones carried in Cc and Bcc are taken back out and
     * passed under their own keys instead.
     *
     * @return list<Address>
     */
    private function recipients(Email $email, Envelope $envelope): array
    {
        $copied = array_map(
            static fn (Address $address): string => $address->getAddress(),
            array_merge($email->getCc(), $email->getBcc()),
        );

        return array_values(array_filter(
            $envelope->getRecipients(),
            static fn (Address $address): bool => ! in_array($address->getAddress(), $copied, true),
        ));
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return list<array<string, string>>
     */
    private function addresses(array $addresses): array
    {
        return array_map($this->address(...), array_values($addresses));
    }

    /**
     * @return array<string, string>
     */
    private function address(Address $address): array
    {
        return array_filter([
            'email' => $address->getAddress(),
            // Brevo rejects an empty name outright, and most addresses do not
            // carry one, so the key only appears when there is something in it.
            'name' => $address->getName(),
        ], static fn (string $value): bool => $value !== '');
    }

    /**
     * Files hung off the message, base64'd the way the API wants them.
     *
     * @return list<array<string, string>>
     */
    private function attachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();

            $attachments[] = [
                'content' => base64_encode($attachment->getBody()),
                'name' => $headers->getHeaderParameter('content-disposition', 'filename') ?? 'attachment',
            ];
        }

        return $attachments;
    }

    /**
     * Read one of the message's bodies as a string.
     *
     * Symfony will hold a body as an open stream rather than a string when it
     * was given one — a mailable rendered from a file, most often — and the
     * API needs the content itself, so anything still on a stream is read off
     * it here.
     *
     * @param  resource|string  $content
     */
    private function body($content): string
    {
        if (is_string($content)) {
            return $this->text($content);
        }

        // Only rewind if it can be: a non-seekable stream has a single pass in it,
        // and asking one to rewind is an error rather than a no-op.
        if (stream_get_meta_data($content)['seekable']) {
            rewind($content);
        }

        $read = stream_get_contents($content);

        if ($read === false) {
            throw new TransportException('Could not read the message body to send it.');
        }

        return $this->text($read);
    }

    /**
     * Strip the invalid UTF-8 the API would reject the whole message over.
     *
     * A body is assembled from names, subjects and anything else that came off
     * a form, and one bad byte anywhere in it makes the JSON encode fail — so
     * the send dies before it is attempted, with an error about encoding rather
     * than about mail. Nothing legitimate is lost here: the substitution
     * character only ever replaces a byte that was not text to begin with.
     */
    private function text(string $value): string
    {
        return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }

    /**
     * Turn a refusal into a line worth logging.
     *
     * Brevo states the reason in `message`, with a `code` such as
     * `invalid_parameter` beside it. Falling back to the raw body matters for
     * the failures that do not come from the API itself — a gateway timeout
     * from in front of it answers in HTML, and "the request failed" alone
     * would leave nothing to tell the two apart.
     */
    private function describe(Response $response): string
    {
        $message = $response->json('message');
        $code = $response->json('code');

        if (is_string($message)) {
            return sprintf(
                'Brevo refused the message (HTTP %d%s): %s',
                $response->status(),
                is_string($code) ? ', '.$code : '',
                $message,
            );
        }

        return sprintf(
            'Brevo refused the message (HTTP %d): %s',
            $response->status(),
            trim($response->body()) !== '' ? Str::limit($response->body(), 500) : 'no response body',
        );
    }

    /**
     * Names the transport in logs and in exception messages.
     */
    public function __toString(): string
    {
        return 'brevo+api://api.brevo.com';
    }
}
