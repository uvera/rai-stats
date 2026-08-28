<?php

namespace App\Mcp\Tools;

use App\Models\ProductCategory;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('List the product-category taxonomy for grocery receipt items, with each category\'s keyword rules (case-insensitive substring patterns, highest priority wins) and how many receipt items currently fall under it. Use this together with list_uncategorized_receipt_items to propose new categories/rules for the ProductCategorySeeder.')]
#[IsReadOnly]
class ListProductCategoriesTool extends Tool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $categories = ProductCategory::query()
            ->withCount('items')
            ->with('rules')
            ->orderBy('name')
            ->get()
            ->map(fn (ProductCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'color' => $category->color,
                'item_count' => $category->items_count,
                'rules' => $category->rules
                    ->sortByDesc('priority')
                    ->values()
                    ->map(fn ($rule) => [
                        'pattern' => $rule->pattern,
                        'priority' => $rule->priority,
                    ])
                    ->all(),
            ])
            ->all();

        return Response::structured(['categories' => $categories]);
    }
}
