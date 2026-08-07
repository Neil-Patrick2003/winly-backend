<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
}
