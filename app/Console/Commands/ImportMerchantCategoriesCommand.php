<?php

namespace App\Console\Commands;

use App\Support\MerchantCategoryData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Applies a JSON file (from merchant-categories:export, or hand-edited in
 * the same shape) to the database. Additive - never deletes categories/rules
 * missing from the file. Run transactions:recategorize afterwards to apply
 * any new/changed rules to existing transactions.
 */
class ImportMerchantCategoriesCommand extends Command
{
    protected $signature = 'merchant-categories:import {path : Path to a JSON file in the merchant-categories:export shape}';

    protected $description = 'Imports categories/rules from a JSON file';

    public function handle(MerchantCategoryData $data): int
    {
        $path = $this->argument('path');

        if (! File::exists($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $decoded = json_decode(File::get($path), true);

        if (! is_array($decoded)) {
            $this->error('That file is not valid JSON.');

            return self::FAILURE;
        }

        $result = $data->import($decoded);

        $this->info("Imported {$result['categories']} categories and {$result['rules']} rules.");
        $this->line('Run `php artisan transactions:recategorize` to apply any new rules to existing transactions.');

        return self::SUCCESS;
    }
}
