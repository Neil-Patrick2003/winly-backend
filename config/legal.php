<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legal Document Details
    |--------------------------------------------------------------------------
    |
    | The details the Terms of Service and Privacy Policy are written around.
    | They live here rather than inside the Blade views so that changing the
    | contact address or the trading name does not mean editing prose.
    |
    | THE DEFAULTS BELOW ARE PLACEHOLDERS. Fill them in — and have a lawyer read
    | the two documents — before submitting to the App Store or taking real
    | users. A published policy that does not match reality is worse than none.
    |
    */

    /**
     * The legal entity behind the app. A registered company name if there is
     * one, otherwise the name you trade under.
     */
    'company' => env('LEGAL_COMPANY', 'the Welle team'),

    /**
     * Where reports of abuse, privacy requests and complaints land. Has to be
     * an address somebody actually reads — Apple's review checks it resolves,
     * and the 24-hour promise in the terms is made against it.
     */
    'contact_email' => env('LEGAL_CONTACT_EMAIL', 'app.welle@gmail.com'),

    /**
     * Whose law governs the terms. Your country, or your state or province
     * where that is the relevant unit.
     */
    'jurisdiction' => env('LEGAL_JURISDICTION', 'the Philippines'),

    /**
     * The minimum age for an account. 13 is the usual floor; 16 is safer if you
     * expect users in the EU, where GDPR sets the bar for consent higher.
     */
    'minimum_age' => env('LEGAL_MINIMUM_AGE', 13),

    /**
     * How long deleted content can survive in backups. Must be the truth about
     * your actual backup rotation, not an aspiration.
     */
    'backup_retention_days' => env('LEGAL_BACKUP_RETENTION_DAYS', 30),

    /**
     * When each document last changed, shown at the top of the page.
     *
     * Set by hand: it is the date the wording changed, which is not the same as
     * the date the file was touched, and it is the thing a dated acceptance on
     * a user record is a record of.
     */
    'terms_updated_at' => env('LEGAL_TERMS_UPDATED_AT', '7 August 2026'),

    'privacy_updated_at' => env('LEGAL_PRIVACY_UPDATED_AT', '7 August 2026'),

];
