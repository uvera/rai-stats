<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesStatsScope;
use App\Models\Transaction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('The largest transactions by absolute amount in a date range.')]
#[IsReadOnly]
class GetLargestTransactionsTool extends Tool
{
    use ResolvesStatsScope;

    public function handle(Request $request): Response|ResponseFactory
    {
        $transactions = collect($this->statsFor($request)->largestTransactions((int) $request->get('limit', 10)))
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'date' => $transaction->date->toDateString(),
                'amount_cents' => $transaction->amount_cents,
                'currency_code' => $transaction->currency_code,
                'place' => $transaction->place,
                'description' => $transaction->description,
                'account_description' => $transaction->account->description,
            ])
            ->all();

        return Response::structured(['transactions' => $transactions]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->rangeSchema($schema),
            'limit' => $schema->integer()->description('Number of largest transactions to return.')->default(10),
        ];
    }
}
