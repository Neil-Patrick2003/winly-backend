<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\ListMyCirclesTool;
use App\Mcp\Tools\MemberActivityTool;
use App\Mcp\Tools\MembersWithoutPostsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

/**
 * The MCP server AI apps (Claude, ChatGPT, Cursor…) connect to.
 *
 * Every call runs as the person who made the token, and only ever sees the
 * circles that person runs. Read-only: nothing here posts, edits or deletes.
 */
#[Name('welle')]
#[Version('0.1.0')]
#[Instructions(<<<'TXT'
Read-only access to the welle circles the connected user runs (as owner or co-owner): their members and who has been posting wins into them.
Start with list_my_circles to get circle ids. Use members_without_posts for "who didn't post yesterday / on a day", and member_activity for activity over a period.
Dates are YYYY-MM-DD in the app's timezone (returned as `timezone`). Nothing can be changed through this server.
TXT)]
class WelleServer extends Server
{
    /**
     * The token ability that marks a Sanctum token as an AI integration token.
     *
     * Such a token opens this server and nothing else, and the mobile app's
     * tokens never open this server.
     */
    public const TOKEN_ABILITY = 'mcp';

    protected array $tools = [
        ListMyCirclesTool::class,
        MembersWithoutPostsTool::class,
        MemberActivityTool::class,
    ];

    protected array $resources = [
        //
    ];

    protected array $prompts = [
        //
    ];
}
