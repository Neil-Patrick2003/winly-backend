<?php

test('the privacy policy is public', function () {
    config(['legal.contact_email' => 'privacy@example.com']);

    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('Profile → Settings → Delete account', false)
        ->assertSee('privacy@example.com');
});

test('the terms are public', function () {
    config(['legal.contact_email' => 'privacy@example.com']);

    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee('zero tolerance')
        ->assertSee(route('legal.privacy'));
});

test('the account deletion page is public and works without the app', function () {
    config(['legal.contact_email' => 'privacy@example.com']);

    $this->get('/delete-account')
        ->assertOk()
        ->assertSee('Delete your account')
        ->assertSee('Profile → Settings → Delete account', false)
        ->assertSee('mailto:privacy@example.com', false)
        ->assertSee('What is deleted')
        ->assertSee('30 days');
});

test('the pages still render without a contact address', function () {
    config(['legal.contact_email' => null]);

    $this->get(route('legal.privacy'))->assertOk();
    $this->get(route('legal.terms'))->assertOk();
    $this->get(route('legal.delete-account'))
        ->assertOk()
        ->assertDontSee('Without the app');
});
