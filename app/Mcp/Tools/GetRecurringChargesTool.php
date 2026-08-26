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

#[Description('Places with a recurring, roughly-stable charge over several distinct months in a date range - a heuristic for subscriptions/regular bills, not a precise match.')]
#[IsReadOnly]
class GetRecurringChargesTool extends Tool
{
    use ResolvesStatsScope;

    public function handle(Request $request): Response|ResponseFactory
    {
        return Response::structured([
            'charges' => $this->statsFor($request)->recurringCharges((int) $request->get('min_months', 3)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->rangeSchema($schema),
            'min_months' => $schema->integer()->description('Minimum number of distinct months a place must recur in to count as recurring.')->default(3),
        ];
    }
}
