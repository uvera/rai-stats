<?php

namespace App\Console\Commands;

use App\Enums\CategorySource;
use App\Models\GroceryReceiptItem;
use App\Support\ProductCategorizer;
use Illuminate\Console\Command;

class RecategorizeGroceryItemsCommand extends Command
{
    protected $signature = 'groceries:recategorize-items {--dry-run} {--force}';

    protected $description = 'Re-applies product category rules to every grocery receipt item (run after editing rules)';

    public function handle(): int
    {
        $categorizer = new ProductCategorizer;
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $updated = 0;
        $skippedManual = 0;

        GroceryReceiptItem::query()->orderBy('id')->chunkById(500, function ($items) use ($categorizer, $force, $dryRun, &$updated, &$skippedManual) {
            foreach ($items as $item) {
                if (! $force && $item->category_source === CategorySource::Manual) {
                    $skippedManual++;

                    continue;
                }

                $categoryId = $categorizer->categorize($item->name);

                if ($categoryId !== $item->product_category_id) {
                    if (! $dryRun) {
                        $item->update([
                            'product_category_id' => $categoryId,
                            'category_source' => $categoryId !== null ? CategorySource::Rule : null,
                        ]);
                    }

                    $updated++;
                }
            }
        });

        $this->info("Recategorized {$updated} item(s), skipped {$skippedManual} manually-categorized item(s).");

        return self::SUCCESS;
    }
}
