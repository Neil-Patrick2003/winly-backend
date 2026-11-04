<?php

use App\Mcp\Servers\WelleServer;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the AI integration page lists only the user\'s own AI tokens', function () {
    $user = User::factory()->create();
    $user->createToken('Claude', [WelleServer::TOKEN_ABILITY]);
    $user->createToken('iPhone');
    User::factory()->create()->createToken('Not mine', [WelleServer::TOKEN_ABILITY]);

    $this->actingAs($user)
        ->get(route('ai-integration.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai-integration')
            ->where('mcpUrl', route('mcp'))
            ->has('tokens', 1)
            ->where('tokens.0.name', 'Claude')
            ->where('newToken', null)
            ->where('runsCircles', false));
});

test('creating a token shows it once and it opens the MCP server', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->post(route('ai-integration.tokens.store'), ['name' => 'Claude on my laptop']);

    $response->assertRedirect(route('ai-integration.edit'));

    $token = $user->tokens()->sole();
    expect($token->abilities)->toBe([WelleServer::TOKEN_ABILITY])
        ->and($token->expires_at)->not->toBeNull();

    $page = $this->actingAs($user)
        ->withSession(['newAiToken' => session('newAiToken')])
        ->get(route('ai-integration.edit'));

    $page->assertInertia(fn (Assert $page) => $page
        ->where('newToken', fn (string $plain) => str_starts_with($plain, $token->id.'|')));

    $this->app['auth']->forgetGuards();

    $this->postJson(route('mcp'), [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => 'list_my_circles', 'arguments' => (object) []],
    ], ['Authorization' => 'Bearer '.$page->inertiaProps('newToken')])->assertOk()->assertSee('circles');
});

test('a token needs a name', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('ai-integration.tokens.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('a user can revoke their own token but not someone else\'s or an app token', function () {
    $user = User::factory()->create();
    $mine = $user->createToken('Claude', [WelleServer::TOKEN_ABILITY])->accessToken;
    $appToken = $user->createToken('iPhone')->accessToken;
    $theirs = User::factory()->create()->createToken('Theirs', [WelleServer::TOKEN_ABILITY])->accessToken;

    $this->actingAs($user)->delete(route('ai-integration.tokens.destroy', $theirs->id))->assertNotFound();
    $this->actingAs($user)->delete(route('ai-integration.tokens.destroy', $appToken->id))->assertNotFound();

    $this->actingAs($user)
        ->delete(route('ai-integration.tokens.destroy', $mine->id))
        ->assertRedirect(route('ai-integration.edit'));

    expect($mine->fresh())->toBeNull()
        ->and($appToken->fresh())->not->toBeNull()
        ->and($theirs->fresh())->not->toBeNull();
});
