<?php

/*
 * The Terms and the Privacy Policy.
 *
 * These two URLs are handed to App Store Connect and linked from the sign-up
 * screen, so the thing worth guarding is that they answer to somebody who is
 * not signed in and has no session — a reviewer, a crawler, a person deciding
 * whether to register at all.
 */

test('the terms are readable without an account', function () {
    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee(config('legal.contact_email'));
});

test('the privacy policy is readable without an account', function () {
    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee(config('legal.contact_email'));
});

test('the terms carry the objectionable content clause review looks for', function () {
    // Apple will not pass an app carrying user content without a stated policy
    // on abuse and a means of acting on it. Asserted here so a later tidy of
    // the wording cannot quietly drop the clause that gets the app approved.
    $this->get(route('legal.terms'))
        ->assertSee('no tolerance for objectionable content', false)
        ->assertSee('24 hours')
        ->assertSee('report content and block other people');
});

test('each page links to the other', function () {
    $this->get(route('legal.terms'))->assertSee(route('legal.privacy'), false);
    $this->get(route('legal.privacy'))->assertSee(route('legal.terms'), false);
});

test('the pages render no unresolved placeholders', function (string $route) {
    $html = $this->get(route($route))->assertOk()->getContent();

    expect($html)->not->toContain('{{');
    expect($html)->not->toContain('Undefined');
})->with(['legal.terms', 'legal.privacy']);
