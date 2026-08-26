<?php

namespace App\Mcp\Tools;

use App\Models\Account;
use App\Support\McpTokenScope;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List bank accounts visible to this token (its own accounts, or every family member\'s accounts for a family-scoped token).')]
#[IsReadOnly]
class ListAccountsTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $userId = McpTokenScope::resolveUserId($request);
        $isFamily = McpTokenScope::isFamily($request);

        $accounts = Account::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->with('user')
            ->orderBy('description')
            ->get()
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'number' => $account->number,
                'description' => $account->description,
                'currency_code' => $account->currency_code,
                'owner' => $isFamily ? $account->user->name : null,
            ])
            ->all();

        return Response::structured(['accounts' => $accounts]);
    }
}
