<?php

namespace App\Mcp\Tools;

use App\Enums\ReceiptProvider;
use App\Mcp\Tools\Concerns\ScopesGroceryItems;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Distinct grocery receipt item names that no product-category rule currently matches, aggregated with how often each was bought and total spend (integer cents). This is the raw material for inferring new ProductCategorySeeder categories and keyword rules - the item names are as printed on Serbian fiscal receipts (abbreviated, uppercase).')]
#[IsReadOnly]
class ListUncategorizedReceiptItemsTool extends Tool
{
    use ScopesGroceryItems;

    public function handle(Request $request): Response|ResponseFactory
    {
        $provider = $request->get('provider')
            ? ReceiptProvider::from($request->get('provider'))
            : null;

        $minOccurrences = max((int) $request->get('min_occurrences', 1), 1);
        $limit = min((int) $request->get('limit', 200), 1000);

        $base = fn (): Builder => $this->scopedItems($request)
            ->whereNull('product_category_id')
            ->when($provider !== null, fn (Builder $query) => $query->whereHas(
                'receipt',
                fn (Builder $receipt) => $receipt->where('provider', $provider),
            ));

        $names = $base()
            ->groupBy('name')
            ->selectRaw('name, COUNT(*) as times_bought, SUM(total_cents) as spend_cents')
            ->havingRaw('COUNT(*) >= ?', [$minOccurrences])
            ->orderByDesc('spend_cents')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'times_bought' => (int) $row->times_bought,
                'spend_cents' => (int) $row->spend_cents,
            ])
            ->all();

        return Response::structured([
            'total_uncategorized_items' => $base()->count(),
            'distinct_uncategorized_names' => $base()->distinct('name')->count('name'),
            'returned_names' => count($names),
            'names' => $names,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'provider' => $schema->string()->enum(ReceiptProvider::class)->description('Restrict to one grocery provider.'),
            'min_occurrences' => $schema->integer()->description('Only include names bought at least this many times. Default 1.')->default(1),
            'limit' => $schema->integer()->description('Max distinct names to return, ordered by spend descending (default 200, max 1000).')->default(200),
        ];
    }
}
