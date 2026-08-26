<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesStatsScope;
use App\Support\McpTokenScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Family-wide per-user spend/income leaderboard for a date range. Requires a family-scoped token.')]
#[IsReadOnly]
class GetLeaderboardTool extends Tool
{
    use ResolvesStatsScope;

    public function handle(Request $request): Response|ResponseFactory
    {
        if (! McpTokenScope::isFamily($request)) {
            return Response::error('This tool requires a family-scoped token. The token used has "just me" scope.');
        }

        return Response::structured([
            'leaderboard' => $this->statsFor($request)->leaderboard(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->rangeSchema($schema);
    }
}
