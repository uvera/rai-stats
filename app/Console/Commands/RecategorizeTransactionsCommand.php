<?php

namespace App\Console\Commands;

use App\Enums\CategorySource;
use App\Models\Transaction;
use App\Support\MerchantCategorizer;
use Illuminate\Console\Command;

class RecategorizeTransactionsCommand extends Command
{
    protected $signature = 'transactions:recategorize {--dry-run} {--force}';

    protected $description = 'Re-applies merchant category rules to every transaction (run after editing rules)';

    public function handle(): int
    {
        $categorizer = new MerchantCategorizer;
        $force = (bool) $this->option('force');
        $dryRun = (bool) $this->option('dry-run');

        $updated = 0;
        $skippedManual = 0;

        Transaction::query()->orderBy('id')->chunkById(500, function ($transactions) use ($categorizer, $force, $dryRun, &$updated, &$skippedManual) {
            foreach ($transactions as $transaction) {
                if (! $force && $transaction->category_source === CategorySource::Manual) {
                    $skippedManual++;

                    continue;
                }

                $categoryId = $categorizer->categorize($transaction->place);

                if ($categoryId !== $transaction->category_id) {
                    if (! $dryRun) {
                        $transaction->update([
                            'category_id' => $categoryId,
                            'category_source' => $categoryId !== null ? CategorySource::Rule : null,
                        ]);
                    }

                    $updated++;
                }
            }
        });

        $this->info("Recategorized {$updated} transaction(s), skipped {$skippedManual} manually-categorized transaction(s).");

        return self::SUCCESS;
    }
}
