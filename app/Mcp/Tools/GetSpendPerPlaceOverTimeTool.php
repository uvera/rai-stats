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

#[Description('Spend per period (month/quarter/year) for the top spend places in a date range - useful for spotting trends per merchant.')]
#[IsReadOnly]
class GetSpendPerPlaceOverTimeTool extends Tool
{
    use ResolvesStatsScope;

    public function handle(Request $request): Response|ResponseFactory
    {
        return Response::structured(
            $this->statsFor($request)->spendPerPlaceOverTime((int) $request->get('top_places', 5))
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->rangeSchema($schema),
            'top_places' => $schema->integer()->description('Number of top spend places to include.')->default(5),
        ];
    }
}
