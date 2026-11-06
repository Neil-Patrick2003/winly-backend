<?php

use App\Mcp\Servers\WelleServer;
use App\Mcp\Tools\ListMyCirclesTool;
use App\Mcp\Tools\MemberActivityTool;
use App\Mcp\Tools\MembersWithoutPostsTool;
use App\Models\Circle;
use App\Models\CircleMembership;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // 10:00 on 4 November in Manila, so "yesterday" is 3 November there.
    $this->travelTo(Carbon::parse('2026-11-04 10:00', 'Asia/Manila'));

    $this->owner = User::factory()->create();
    $this->circle = Circle::factory()->create(['owner_id' => $this->owner->id, 'name' => 'Morning Crew']);

    CircleMembership::factory()->create([
        'circle_id' => $this->circle->id,
        'user_id' => $this->owner->id,
        'role' => CircleMembership::ROLE_OWNER,
        'joined_at' => now()->subMonth(),
    ]);

    $this->join = function (Circle $circle, string $name, ?CarbonInterface $joinedAt = null): User {
        $user = User::factory()->create(['full_name' => $name]);

        CircleMembership::factory()->create([
            'circle_id' => $circle->id,
            'user_id' => $user->id,
            'joined_at' => $joinedAt ?? now()->subMonth(),
        ]);

        return $user;
    };
});

/**
 * Calls the MCP server over HTTP the way an AI app does.
 *
 * @param  array<string, mixed>  $arguments
 */
function callMcp(string $url, string $tool, array $arguments = [], array $headers = []): TestResponse
{
    return test()->postJson($url, [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/call',
        'params' => ['name' => $tool, 'arguments' => (object) $arguments],
    ], $headers);
}

test('lists only the members who did not post into the circle yesterday', function () {
    $posted = ($this->join)($this->circle, 'Ana Posted');
    $quiet = ($this->join)($this->circle, 'Ben Quiet');
    $postedToday = ($this->join)($this->circle, 'Cara Today');
    ($this->join)($this->circle, 'Dan Newcomer', now());

    postInCircle($this->circle, $posted, ['created_at' => Carbon::parse('2026-11-03 21:00', 'Asia/Manila')->utc()]);
    postInCircle($this->circle, $postedToday, ['created_at' => Carbon::parse('2026-11-04 07:00', 'Asia/Manila')->utc()]);

    // Somewhere else entirely: posting into another circle does not count.
    $elsewhere = Circle::factory()->create();
    postInCircle($elsewhere, $quiet, ['created_at' => Carbon::parse('2026-11-03 12:00', 'Asia/Manila')->utc()]);

    $response = WelleServer::actingAs($this->owner)->tool(MembersWithoutPostsTool::class);

    $response->assertOk()
        ->assertSee(['"date":"2026-11-03"', 'Ben Quiet', 'Cara Today', '"posted_count":1'])
        ->assertDontSee(['Ana Posted', 'Dan Newcomer', $this->owner->full_name]);
});

test('checks a given day and a single circle', function () {
    $member = ($this->join)($this->circle, 'Ana Member');
    postInCircle($this->circle, $member, ['created_at' => Carbon::parse('2026-11-03 09:00', 'Asia/Manila')->utc()]);

    WelleServer::actingAs($this->owner)
        ->tool(MembersWithoutPostsTool::class, ['date' => '2026-11-02', 'circle_id' => $this->circle->id])
        ->assertOk()
        ->assertSee(['"date":"2026-11-02"', 'Ana Member']);
});

test('refuses circles the user does not run', function () {
    $someoneElses = Circle::factory()->create();

    // Being a plain member is not enough.
    CircleMembership::factory()->create(['circle_id' => $someoneElses->id, 'user_id' => $this->owner->id]);

    WelleServer::actingAs($this->owner)
        ->tool(MembersWithoutPostsTool::class, ['circle_id' => $someoneElses->id])
        ->assertHasErrors(['You do not run that circle.']);

    WelleServer::actingAs($this->owner)
        ->tool(ListMyCirclesTool::class)
        ->assertOk()
        ->assertSee('Morning Crew')
        ->assertDontSee($someoneElses->name);
});

test('member activity counts posts and distinct days over the period', function () {
    $member = ($this->join)($this->circle, 'Ana Active');

    postInCircle($this->circle, $member, ['created_at' => Carbon::parse('2026-11-02 08:00', 'Asia/Manila')->utc()]);
    postInCircle($this->circle, $member, ['created_at' => Carbon::parse('2026-11-02 20:00', 'Asia/Manila')->utc()]);
    postInCircle($this->circle, $member, ['created_at' => Carbon::parse('2026-11-04 08:00', 'Asia/Manila')->utc()]);

    WelleServer::actingAs($this->owner)
        ->tool(MemberActivityTool::class)
        ->assertOk()
        ->assertSee(['"from":"2026-10-29"', '"to":"2026-11-04"', '"posts_in_period":3', '"days_posted_in_period":2']);
});

test('an AI integration token reaches the server through the bearer header', function () {
    ($this->join)($this->circle, 'Ben Quiet');

    $token = $this->owner->createToken('Claude', [WelleServer::TOKEN_ABILITY])->plainTextToken;

    callMcp(route('mcp'), 'members_without_posts', [], ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertSee('Ben Quiet');
});

test('the server refuses missing, revoked and mobile app tokens', function () {
    callMcp(route('mcp'), 'list_my_circles')->assertUnauthorized();
    callMcp(route('mcp'), 'list_my_circles', [], ['Authorization' => 'Bearer 1|not-a-real-token'])->assertUnauthorized();

    $appToken = $this->owner->createToken('iPhone')->plainTextToken;
    callMcp(route('mcp'), 'list_my_circles', [], ['Authorization' => "Bearer {$appToken}"])->assertUnauthorized();

    $revoked = $this->owner->createToken('Claude', [WelleServer::TOKEN_ABILITY]);
    $revoked->accessToken->delete();
    callMcp(route('mcp'), 'list_my_circles', [], ['Authorization' => "Bearer {$revoked->plainTextToken}"])->assertUnauthorized();
});

test('an AI integration token cannot open the mobile API', function () {
    $token = $this->owner->createToken('Claude', [WelleServer::TOKEN_ABILITY])->plainTextToken;

    $this->getJson(route('api.v1.user'), ['Authorization' => "Bearer {$token}"])->assertUnauthorized();

    $appToken = $this->owner->createToken('iPhone')->plainTextToken;
    $this->app['auth']->forgetGuards();

    $this->getJson(route('api.v1.user'), ['Authorization' => "Bearer {$appToken}"])->assertOk();
});

test('a co-owner sees the circles they were given the run of', function () {
    $coOwner = User::factory()->create();
    $shared = Circle::factory()->create(['name' => 'Shared Crew']);

    CircleMembership::factory()->create([
        'circle_id' => $shared->id,
        'user_id' => $coOwner->id,
        'role' => CircleMembership::ROLE_OWNER,
        'joined_at' => now()->subMonth(),
    ]);

    ($this->join)($shared, 'Ben Quiet');

    WelleServer::actingAs($coOwner)
        ->tool(ListMyCirclesTool::class)
        ->assertOk()
        ->assertSee('Shared Crew');

    WelleServer::actingAs($coOwner)
        ->tool(MembersWithoutPostsTool::class, ['circle_id' => $shared->id])
        ->assertOk()
        ->assertSee(['Shared Crew', 'Ben Quiet']);
});
