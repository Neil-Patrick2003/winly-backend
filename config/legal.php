<?php

/*
 * The details the Privacy Policy and the Terms are written around.
 *
 * Kept here rather than in the pages so changing who runs the app, or where to
 * write to, is an `.env` edit and not a rewrite. Bump `updated` whenever the
 * wording of either page changes: both stores, and the law, expect people to
 * be able to see when the terms they agreed to last moved.
 */
return [
    // Who runs Welle, as it should appear to the people using it.
    'operator' => env('LEGAL_OPERATOR', env('APP_NAME', 'Welle')),

    // Where privacy questions and deletion requests go. Must be a real inbox
    // somebody reads: both app stores check that this is reachable.
    'contact_email' => env('LEGAL_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS')),

    // Whose law the Terms are under, and which privacy law the policy answers to.
    'country' => env('LEGAL_COUNTRY', 'the Philippines'),

    // The youngest someone can be to make an account.
    'minimum_age' => (int) env('LEGAL_MINIMUM_AGE', 13),

    // When either document last changed, shown at the top of both.
    'updated' => '2026-11-05',
];
