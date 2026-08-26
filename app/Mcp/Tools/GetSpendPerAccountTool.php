<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesStatsScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Spend and income totals per account for a date range.')]
#[IsReadOnly]
class GetSpendPerAccountTool extends Tool
{
    use ResolvesStatsScope;

    public function handle(Request $request): Response|ResponseFactory
    {
        return Response::structured([
            'accounts' => $this->statsFor($request)->spendPerAccount(),
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
