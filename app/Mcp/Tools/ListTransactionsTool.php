<?php

namespace App\Mcp\Tools;

use App\Enums\TransactionType;
use App\Models\Transaction;
use App\Support\McpTokenScope;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List transactions in scope (own, or every family member\'s for a family-scoped token), filterable by date range, account, type, and place.')]
#[IsReadOnly]
class ListTransactionsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $userId = McpTokenScope::resolveUserId($request);
        $isFamily = McpTokenScope::isFamily($request);

        $limit = min((int) $request->get('limit', 50), 200);
        $offset = max((int) $request->get('offset', 0), 0);

        $transactions = Transaction::query()
            ->excludingReserved()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when($request->get('from'), fn ($query, $value) => $query->where('date', '>=', $value))
            ->when($request->get('to'), fn ($query, $value) => $query->where('date', '<=', $value))
            ->when($request->get('account_id'), fn ($query, $value) => $query->where('account_id', $value))
            ->when($request->get('type'), fn ($query, $value) => $query->where('type', $value))
            ->when($request->get('place'), fn ($query, $value) => $query->where('place', 'ilike', "%{$value}%"))
            ->with(['account', 'user'])
            ->orderByDesc('date')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(fn (Transaction $transaction) => [
                'id' => $transaction->id,
                'account_id' => $transaction->account_id,
                'account_number' => $transaction->account->number,
                'owner' => $isFamily ? $transaction->user->name : null,
                'date' => $transaction->date->toDateString(),
                'amount_cents' => $transaction->amount_cents,
                'currency_code' => $transaction->currency_code,
                'place' => $transaction->place,
                'description' => $transaction->description,
                'type' => $transaction->type->value,
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
            'from' => $schema->string()->description('Only transactions on or after this date (Y-m-d).'),
            'to' => $schema->string()->description('Only transactions on or before this date (Y-m-d).'),
            'account_id' => $schema->integer()->description('Restrict to a single account id.'),
            'type' => $schema->string()->enum(TransactionType::class)->description('Restrict to this transaction type.'),
            'place' => $schema->string()->description('Case-insensitive substring match against the transaction place.'),
            'limit' => $schema->integer()->description('Max rows to return (default 50, max 200).')->default(50),
            'offset' => $schema->integer()->description('Rows to skip, for pagination.')->default(0),
        ];
    }
}
