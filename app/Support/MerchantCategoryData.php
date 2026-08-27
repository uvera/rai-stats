<?php

namespace App\Support;

use App\Models\Category;
use App\Models\MerchantCategoryRule;

/**
 * Converts categories/rules between the database and a plain JSON shape,
 * used both ways: exporting the current database state for download/backup,
 * and importing a JSON file (the bundled seed fixture, or one a user
 * downloaded/edited/re-uploaded) back into the database. Import is additive
 * (updateOrCreate) - it never deletes categories/rules missing from the
 * given data.
 */
readonly class MerchantCategoryData
{
    /**
     * @return array{categories: array<int, array{name: string, color: string|null, rules: array<int, array{pattern: string, priority: int}>}>}
     */
    public function export(): array
    {
        $categories = Category::query()
            ->with(['rules' => fn ($query) => $query->orderByDesc('priority')->orderBy('id')])
            ->orderBy('name')
            ->get();

        return [
            'categories' => $categories->map(fn (Category $category) => [
                'name' => $category->name,
                'color' => $category->color,
                'rules' => $category->rules->map(fn (MerchantCategoryRule $rule) => [
                    'pattern' => $rule->pattern,
                    'priority' => $rule->priority,
                ])->all(),
            ])->all(),
        ];
    }

    /**
     * @param  array{categories?: array<int, array{name: string, color?: string|null, rules?: array<int, array{pattern: string, priority?: int}>}>}  $data
     * @return array{categories: int, rules: int}
     */
    public function import(array $data): array
    {
        $categoryCount = 0;
        $ruleCount = 0;

        foreach ($data['categories'] ?? [] as $categoryData) {
            $category = Category::query()->updateOrCreate(
                ['name' => $categoryData['name']],
                ['color' => $categoryData['color'] ?? null],
            );
            $categoryCount++;

            foreach ($categoryData['rules'] ?? [] as $ruleData) {
                MerchantCategoryRule::query()->updateOrCreate(
                    ['category_id' => $category->id, 'pattern' => $ruleData['pattern']],
                    ['priority' => $ruleData['priority'] ?? 0],
                );
                $ruleCount++;
            }
        }

        return ['categories' => $categoryCount, 'rules' => $ruleCount];
    }
}
