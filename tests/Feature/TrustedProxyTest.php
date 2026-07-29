<?php

use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::get('/proxy-scheme-probe', fn () => [
        'secure' => request()->isSecure(),
        'url' => url('/fonts/example.woff2'),
    ]);
});

it('honours the forwarded protocol header from a tunnel or load balancer', function (): void {
    $response = $this->get('/proxy-scheme-probe', ['X-Forwarded-Proto' => 'https'])->assertOk();

    expect($response->json('secure'))->toBeTrue()
        ->and($response->json('url'))->toStartWith('https://');
});

it('serves plain http when no forwarded protocol header is present', function (): void {
    $response = $this->get('/proxy-scheme-probe')->assertOk();

    expect($response->json('secure'))->toBeFalse()
        ->and($response->json('url'))->toStartWith('http://');
});
