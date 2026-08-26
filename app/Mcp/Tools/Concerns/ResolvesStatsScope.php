<?php

namespace App\Mcp\Tools\Concerns;

use App\Support\McpTokenScope;
use App\Support\TransactionStats;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;

/**
 * Shared scope/date-range resolution for MCP tools built on top of
 * TransactionStats, so period-grouping/account/date defaults aren't
 * duplicated per tool (mirrors the AbstractStatsPage defaults: 6 months
 * back from start of month, through today, grouped by month).
 */
trait ResolvesStatsScope
{
    protected function statsFor(Request $request): TransactionStats
    {
        return new TransactionStats(
            userId: McpTokenScope::resolveUserId($request),
            from: $this->parseDate($request->get('from'), now()->subMonths(6)->startOfMonth()->toImmutable()),
            to: $this->parseDate($request->get('to'), now()->toImmutable()),
            period: $request->get('period', 'month'),
            accountIds: $request->get('account_ids') ?: null,
        );
    }

    private function parseDate(?string $value, CarbonImmutable $default): CarbonImmutable
    {
        return $value ? CarbonImmutable::parse($value) : $default;
    }

    /**
     * @return array<string, Type>
     */
    protected function rangeSchema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->description('Start date (Y-m-d). Defaults to 6 months ago, start of month.'),
            'to' => $schema->string()->description('End date (Y-m-d). Defaults to today.'),
            'period' => $schema->string()->enum(['month', 'quarter', 'year'])->description('Grouping period for time-bucketed results.')->default('month'),
            'account_ids' => $schema->array()->items($schema->integer())->description('Restrict to these account ids. Omit for all accounts in scope.'),
        ];
    }
}
