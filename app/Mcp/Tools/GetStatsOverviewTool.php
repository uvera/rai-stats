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

#[Description('Overview stats for a date range: transaction count, average spend per currency, and ATM/cash withdrawal totals per currency. Amounts are integer cents, grouped by currency_code - never sum across currencies.')]
#[IsReadOnly]
class GetStatsOverviewTool extends Tool
{
    use ResolvesStatsScope;

    public function handle(Request $request): Response|ResponseFactory
    {
        $overview = $this->statsFor($request)->overview();

        return Response::structured([
            'transaction_count' => $overview['transaction_count'],
            'average_spend_cents_by_currency' => $overview['average_spend_cents'],
            'atm_withdrawal_cents_by_currency' => $overview['atm_withdrawal_cents'],
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
