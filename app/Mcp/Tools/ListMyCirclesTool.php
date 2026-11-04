<?php

namespace App\Mcp\Tools;

use App\Models\Circle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_my_circles')]
#[Description('Lists the circles you run (as owner or co-owner), with their ids, member counts and parent circle. Use the ids with the other tools.')]
#[IsReadOnly]
class ListMyCirclesTool extends WelleTool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $circles = $this->ownedCircles($this->user())
            ->with('parent:id,name')
            ->orderBy('name')
            ->get();

        return Response::json([
            'circles' => $circles->map(fn (Circle $circle): array => [
                'id' => $circle->id,
                'name' => $circle->name,
                'tag' => $circle->tag,
                'is_private' => $circle->is_private,
                'members_count' => $circle->members_count,
                'parent' => $circle->parent ? [
                    'id' => $circle->parent->id,
                    'name' => $circle->parent->name,
                ] : null,
            ])->all(),
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
