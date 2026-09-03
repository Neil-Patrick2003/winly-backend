<?php

namespace App\Providers;

use App\Support\BrevoApiTransport;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMail();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        /*
         * A length, and nothing else.
         *
         * Production used to ask for twelve characters with mixed case, a
         * number, a symbol and a check against known breaches, while everywhere
         * else asked for eight. Two rules meant a password that worked in
         * development was rejected on the live site, and the strict one turned
         * signing up into a puzzle — people answer that with `Password1!`,
         * which satisfies every clause and is no harder to guess for it.
         *
         * One rule, the same everywhere, so the app can state it plainly and be
         * right. See `App\Concerns\PasswordValidationRules`.
         */
        Password::defaults(fn (): Password => Password::min(8));
    }

    /**
     * Teach the mailer to send through Brevo's HTTP API.
     *
     * Laravel ships transports for Postmark, Resend and SES but not for Brevo,
     * which is the account this app actually sends on — so `MAIL_MAILER=brevo`
     * resolves to `App\Support\BrevoApiTransport`, defined here because
     * `Mail::extend` is the only place a driver can be added.
     *
     * The key is read when the mailer is first resolved rather than at boot, so
     * an app configured to log its mail never has to have one. Where it is
     * needed and missing, that is worth stopping over: an empty `api-key`
     * header sends the message to Brevo to be refused, and the failure comes
     * back as a 401 from a queue worker rather than as the configuration
     * mistake it is.
     */
    protected function configureMail(): void
    {
        Mail::extend('brevo', function (array $config): BrevoApiTransport {
            $key = $config['key'] ?? config('services.brevo.key');

            if (! is_string($key) || $key === '') {
                throw new RuntimeException(
                    'BREVO_API_KEY is not set, so mail cannot be sent through Brevo. '
                    .'Set it, or point MAIL_MAILER at another mailer.'
                );
            }

            return new BrevoApiTransport(
                Http::withHeaders([
                    'api-key' => $key,
                    'accept' => 'application/json',
                ])
                    // Short, because the reset endpoint sends inside the
                    // request: this is somebody waiting on a button, not a
                    // worker. Long enough to ride out a slow moment at Brevo,
                    // and well inside where a person gives up and taps again.
                    ->timeout(15)
                    ->connectTimeout(5)
                    // No retries here. A send that fails is cleaned up and
                    // reported by `SendPasswordResetCode`, and retrying inside
                    // a request the user is waiting on only multiplies the
                    // wait before they are told.
                    ->asJson()
            );
        });
    }
}
