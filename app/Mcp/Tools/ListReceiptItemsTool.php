<?php

namespace App\Mcp\Tools;

use App\Enums\ReceiptProvider;
use App\Mcp\Tools\Concerns\ScopesGroceryItems;
use App\Models\GroceryReceiptItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List individual grocery receipt items, filterable by name substring, product category, categorization source, and provider. Use it to sanity-check a proposed keyword rule (what would "mleko" actually match?) or to review how a category is currently populated. Amounts are integer cents.')]
#[IsReadOnly]
class ListReceiptItemsTool extends Tool
{
    use ScopesGroceryItems;

    public function handle(Request $request): Response|ResponseFactory
    {
        $limit = min((int) $request->get('limit', 100), 500);
        $offset = max((int) $request->get('offset', 0), 0);

        $items = $this->scopedItems($request)
            ->with(['productCategory', 'receipt'])
            ->when($request->get('name'), fn (Builder $query, $value) => $query->where('name', 'ilike', "%{$value}%"))
            ->when($request->get('product_category_id'), fn (Builder $query, $value) => $query->where('product_category_id', $value))
            ->when($request->boolean('only_uncategorized'), fn (Builder $query) => $query->whereNull('product_category_id'))
            ->when($request->get('category_source'), fn (Builder $query, $value) => $query->where('category_source', $value))
            ->when($request->get('provider'), fn (Builder $query, $value) => $query->whereHas(
                'receipt',
                fn (Builder $receipt) => $receipt->where('provider', ReceiptProvider::from($value)),
            ))
            ->orderByDesc('id')
            ->limit($limit)
            ->offset($offset)
            ->get()
            ->map(fn (GroceryReceiptItem $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => (float) $item->quantity,
                'total_cents' => $item->total_cents,
                'vat_rate' => $item->vat_rate !== null ? (float) $item->vat_rate : null,
                'product_category' => $item->productCategory?->name,
                'category_source' => $item->category_source?->value,
                'provider' => $item->receipt->provider->value,
                'store_name' => $item->receipt->store_name,
                'purchased_at' => $item->receipt->purchased_at?->toDateString(),
            ])
            ->all();

        return Response::structured(['items' => $items]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Case-insensitive substring match against the item name.'),
            'product_category_id' => $schema->integer()->description('Restrict to items in this product category.'),
            'only_uncategorized' => $schema->boolean()->description('Only items with no product category.')->default(false),
            'category_source' => $schema->string()->enum(['rule', 'manual'])->description('Restrict to items categorized by a rule or manually.'),
            'provider' => $schema->string()->enum(ReceiptProvider::class)->description('Restrict to one grocery provider.'),
            'limit' => $schema->integer()->description('Max rows to return (default 100, max 500).')->default(100),
            'offset' => $schema->integer()->description('Rows to skip, for pagination.')->default(0),
        ];
    }
}
