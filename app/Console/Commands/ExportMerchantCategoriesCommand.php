<?php

namespace App\Console\Commands;

use App\Support\MerchantCategoryData;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Dumps the current categories/rules as JSON - to stdout by default, or to
 * a file with --path. Pair with merchant-categories:import to move the
 * result to another environment, or to hand-edit and re-apply it.
 */
class ExportMerchantCategoriesCommand extends Command
{
    protected $signature = 'merchant-categories:export {--path= : Write to this file instead of stdout}';

    protected $description = 'Exports the current categories/rules as JSON';

    public function handle(MerchantCategoryData $data): int
    {
        $json = json_encode(
            $data->export(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if ($path = $this->option('path')) {
            File::put($path, $json);
            $this->info("Exported to {$path}.");
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
