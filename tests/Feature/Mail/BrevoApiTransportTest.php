<?php

use App\Models\User;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

beforeEach(function () {
    config([
        'mail.default' => 'brevo',
        'mail.mailers.brevo.key' => 'test-api-key',
        'services.brevo.key' => 'test-api-key',
        'mail.from.address' => 'noreply@welle.app',
        'mail.from.name' => 'Welle',
    ]);

    Http::preventStrayRequests();
});

/**
 * Stub Brevo's answer.
 *
 * Set per test rather than once up here, because faked stubs accumulate and
 * the first one registered is the one that answers — a default success set in
 * `beforeEach` would quietly outrank the refusals the tests below are about.
 */
function fakeBrevo(?PromiseInterface $response = null): void
{
    Http::fake([
        'api.brevo.com/*' => $response ?? Http::response(['messageId' => '<abc123@brevo>'], 201),
    ]);
}

/**
 * The configured transport, ready to be handed a message directly.
 */
function brevo(): TransportInterface
{
    return Mail::mailer('brevo')->getSymfonyTransport();
}

/**
 * A message with everything the mapping has to account for.
 */
function message(): Email
{
    return (new Email)
        ->from(new Address('noreply@welle.app', 'Welle'))
        ->to(new Address('neil@example.com', 'Neil'))
        ->subject('123456 is your Welle password reset code')
        ->html('<p>Your code is 123456</p>')
        ->text('Your code is 123456');
}

/**
 * The body of the single request that was posted to Brevo.
 *
 * @return array<string, mixed>
 */
function sentPayload(): array
{
    $payload = null;

    Http::assertSent(function (Request $request) use (&$payload): bool {
        $payload = $request->data();

        return true;
    });

    return $payload;
}

test('a message goes out as one call to the brevo api', function () {
    fakeBrevo();

    brevo()->send(message());

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.brevo.com/v3/smtp/email'
        && $request->method() === 'POST'
        && $request->hasHeader('api-key', 'test-api-key')
        && $request->isJson());
});

test('the sender, recipient, subject and both bodies are mapped over', function () {
    fakeBrevo();

    brevo()->send(message());

    expect(sentPayload())->toMatchArray([
        'sender' => ['email' => 'noreply@welle.app', 'name' => 'Welle'],
        'to' => [['email' => 'neil@example.com', 'name' => 'Neil']],
        'subject' => '123456 is your Welle password reset code',
        'htmlContent' => '<p>Your code is 123456</p>',
        'textContent' => 'Your code is 123456',
    ]);
});

test('an address with no display name is sent without an empty name', function () {
    fakeBrevo();

    brevo()->send(message()->to('plain@example.com'));

    expect(sentPayload()['to'])->toBe([['email' => 'plain@example.com']]);
});

test('the id brevo answers with is recorded against the send', function () {
    fakeBrevo();

    $sent = brevo()->send(message());

    expect($sent->getMessageId())->toBe('<abc123@brevo>');
});

test('carbon copies are sent under their own keys rather than as recipients', function () {
    fakeBrevo();

    brevo()->send(message()->cc('cc@example.com')->bcc('bcc@example.com'));

    $payload = sentPayload();

    // The envelope hands the transport every recipient flattened into one list.
    // Passing that straight through as `to` would print the blind copy in the
    // header of the mail everyone else receives.
    expect($payload['to'])->toBe([['email' => 'neil@example.com', 'name' => 'Neil']])
        ->and($payload['cc'])->toBe([['email' => 'cc@example.com']])
        ->and($payload['bcc'])->toBe([['email' => 'bcc@example.com']]);
});

test('a reply-to address is carried over as a single value', function () {
    fakeBrevo();

    brevo()->send(message()->replyTo(new Address('help@welle.app', 'Welle Support')));

    expect(sentPayload()['replyTo'])->toBe(['email' => 'help@welle.app', 'name' => 'Welle Support']);
});

test('attachments are base64 encoded with their filename', function () {
    fakeBrevo();

    brevo()->send(message()->attach('a,b'.PHP_EOL.'1,2', 'wins.csv', 'text/csv'));

    expect(sentPayload()['attachment'])->toBe([[
        'content' => base64_encode('a,b'.PHP_EOL.'1,2'),
        'name' => 'wins.csv',
    ]]);
});

test('a message with no html body does not send an empty one', function () {
    fakeBrevo();

    $email = (new Email)
        ->from('noreply@welle.app')
        ->to('neil@example.com')
        ->subject('Plain')
        ->text('Text only');

    brevo()->send($email);

    expect(sentPayload())->not->toHaveKey('htmlContent')
        ->and(sentPayload()['textContent'])->toBe('Text only');
});

test('a refusal from the api is raised as a transport failure carrying brevo\'s reason', function () {
    fakeBrevo(Http::response([
        'code' => 'invalid_parameter',
        'message' => 'Sender email is not valid',
    ], 400));

    expect(fn () => brevo()->send(message()))
        ->toThrow(TransportException::class, 'Brevo refused the message (HTTP 400, invalid_parameter): Sender email is not valid');
});

test('a refusal that is not json still says what came back', function () {
    fakeBrevo(Http::response('<html>Gateway Timeout</html>', 504));

    expect(fn () => brevo()->send(message()))
        ->toThrow(TransportException::class, 'Brevo refused the message (HTTP 504)');
});

test('never reaching the api is raised as a transport failure too', function () {
    // Distinguished from a refusal on purpose: it means Brevo never formed an
    // opinion about this message, so a retry is worth making.
    Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

    expect(fn () => brevo()->send(message()))
        ->toThrow(TransportException::class, 'Could not reach the Brevo API');
});

test('sending without an api key fails loudly instead of asking brevo to refuse it', function () {
    config(['mail.mailers.brevo.key' => null, 'services.brevo.key' => null]);

    expect(fn () => brevo())->toThrow(RuntimeException::class, 'BREVO_API_KEY is not set');
});

test('a password reset code is emailed through the api, and the code that arrives works', function () {
    fakeBrevo();

    $user = User::factory()->create(['email' => 'neil@example.com']);

    $this->postJson(route('api.v1.password.email'), ['email' => 'neil@example.com'])
        ->assertOk();

    $payload = sentPayload();

    expect($payload['to'])->toBe([['email' => 'neil@example.com']])
        ->and($payload['subject'])->toMatch('/^\d{6} is your .+ password reset code$/');

    // The digits Brevo was actually handed — not one generated here — proving
    // what leaves the app is what the reset endpoint will accept.
    $code = str($payload['subject'])->before(' ')->value();

    expect($payload['htmlContent'])->toContain($code)
        ->and($payload['textContent'])->toContain($code);

    $this->postJson(route('api.v1.password.update'), [
        'email' => $user->email,
        'code' => $code,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
        'device_name' => 'Test device',
    ])->assertOk()->assertJsonStructure(['user', 'token']);
});

test('a body held as a stream is read off it rather than sent as a handle', function () {
    fakeBrevo();

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, '<p>Your code is 123456</p>');
    rewind($stream);

    brevo()->send(message()->html($stream));

    expect(sentPayload()['htmlContent'])->toBe('<p>Your code is 123456</p>');
});

test('pre-rendered mime is refused rather than sent with its content dropped', function () {
    // Nothing in the app sends one, but the transport signature allows it, and
    // a raw message has no parts to build a JSON body out of.
    $envelope = new Envelope(new Address('noreply@welle.app'), [new Address('neil@example.com')]);

    expect(fn () => brevo()->send(new RawMessage('Subject: Hi'.PHP_EOL.PHP_EOL.'Body'), $envelope))
        ->toThrow(TransportException::class, 'can only send a structured message');

    Http::assertNothingSent();
});
