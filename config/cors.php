<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | CORS is a browser rule, not a server one. The iOS and Android clients are
    | unaffected by anything here — they will keep working whatever this says.
    | It exists for the web build, which the browser will refuse to let talk to
    | this API unless the API says it may.
    |
    */

    /*
     * Only the API. The Inertia pages are same-origin and need no permission
     * to talk to themselves.
     */
    'paths' => ['api/*'],

    'allowed_methods' => ['*'],

    /*
     * Where the web build is served from.
     *
     * `*` while developing, because the origin changes constantly — a tunnel
     * URL, `localhost` on whatever port Metro picked, a phone on the LAN — and
     * chasing it is not a good use of anybody's afternoon.
     *
     * Set `CORS_ALLOWED_ORIGINS` in production to the real origins, comma
     * separated. `*` there would let any site on the internet call this API
     * from a visitor's browser with that visitor's token.
     */
    'allowed_origins' => array_values(array_filter(
        array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', '*')))
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    /*
     * Headers the browser will hand to JavaScript.
     *
     * A browser hides every response header from `fetch` except a short
     * safelist, and `Retry-After` is not on it. The client reads that header to
     * decide how long to wait after a 429 — without this line it reads null in
     * the browser, falls back to a guess, and the rate-limit handling quietly
     * stops being accurate on web while staying correct on native.
     */
    'exposed_headers' => ['Retry-After'],

    'max_age' => 60 * 60 * 24,

    /*
     * False, and deliberately.
     *
     * The API authenticates with Sanctum bearer tokens, not cookies, so the
     * browser never needs to send credentials cross-origin. It also has to be
     * false for `allowed_origins` to accept `*` at all — the two are mutually
     * exclusive by specification.
     */
    'supports_credentials' => false,

];
