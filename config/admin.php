<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Staff Account
    |--------------------------------------------------------------------------
    |
    | The account `AdminSeeder` puts in place. Read through the config rather
    | than by calling `env()` in the seeder itself, because a deployment that
    | has run `config:cache` never parses `.env` again — `env()` would quietly
    | hand back the fallback below and seed the password written into the repo
    | while appearing to honour the one in the environment.
    |
    */

    'email' => env('ADMIN_EMAIL', 'welle_admin@gmail.com'),

    'password' => env('ADMIN_PASSWORD', 'welle_metadigitrading'),

];
