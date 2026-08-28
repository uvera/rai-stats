<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetIncomeVsExpenseTrendTool;
use App\Mcp\Tools\GetLargestTransactionsTool;
use App\Mcp\Tools\GetLeaderboardTool;
use App\Mcp\Tools\GetRecurringChargesTool;
use App\Mcp\Tools\GetSpendPerAccountTool;
use App\Mcp\Tools\GetSpendPerPlaceOverTimeTool;
use App\Mcp\Tools\GetStatsOverviewTool;
use App\Mcp\Tools\GetTopPlacesTool;
use App\Mcp\Tools\ListAccountsTool;
use App\Mcp\Tools\ListProductCategoriesTool;
use App\Mcp\Tools\ListReceiptItemsTool;
use App\Mcp\Tools\ListTransactionsTool;
use App\Mcp\Tools\ListUncategorizedReceiptItemsTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('rai-stats')]
#[Version('1.0.0')]
#[Instructions(<<<'MARKDOWN'
    Query Raiffeisen bank account and transaction data for the authenticated
    token. Each token is scoped to either a single user's own data or the
    whole family's (every user's) data - tools automatically apply that
    scope and never accept a user/scope argument.

    Money amounts are integer cents. Accounts and transactions can hold
    different currencies (currency_code) - never sum or average amounts
    across differing currency codes; monetary results are always grouped
    by currency instead.

    The grocery tools (list_product_categories, list_uncategorized_receipt_items,
    list_receipt_items) expose itemised grocery receipts synced from loyalty
    accounts (Moj Maxi, Metro), scoped the same way. They exist to help refine
    the product-category taxonomy: pull the uncategorized item names, then
    propose new categories and keyword rules for
    database/seeders/ProductCategorySeeder.php. Rules are case-insensitive
    substring matches; after editing the seeder run `php artisan db:seed
    --class=ProductCategorySeeder` and `php artisan groceries:recategorize-items`.
    MARKDOWN
)]
class RaiStatsServer extends Server
{
    protected array $tools = [
        ListAccountsTool::class,
        ListTransactionsTool::class,
        GetStatsOverviewTool::class,
        GetSpendPerAccountTool::class,
        GetTopPlacesTool::class,
        GetSpendPerPlaceOverTimeTool::class,
        GetIncomeVsExpenseTrendTool::class,
        GetRecurringChargesTool::class,
        GetLargestTransactionsTool::class,
        GetLeaderboardTool::class,
        ListProductCategoriesTool::class,
        ListUncategorizedReceiptItemsTool::class,
        ListReceiptItemsTool::class,
    ];
}
