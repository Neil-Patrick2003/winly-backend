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

test('the pages still render without a contact address', function () {
    config(['legal.contact_email' => null]);

    $this->get(route('legal.privacy'))->assertOk();
    $this->get(route('legal.terms'))->assertOk();
});
