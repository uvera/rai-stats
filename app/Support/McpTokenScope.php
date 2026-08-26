<?php

namespace App\Support;

use App\Enums\TokenScope;
use Laravel\Mcp\Request;
use RuntimeException;

/**
 * Resolves the effective userId (TransactionStats semantics: null = family,
 * int = that user only) from the Sanctum token ability that authenticated
 * the current MCP request. Every scoped MCP tool goes through this instead
 * of re-deriving scope from $request->user() by hand.
 */
final class McpTokenScope
{
    public static function resolveUserId(Request $request): ?int
    {
        return self::resolve($request) === TokenScope::Family ? null : $request->user()->id;
    }

    public static function resolve(Request $request): TokenScope
    {
        $user = $request->user();

        if ($user === null) {
            throw new RuntimeException('MCP request is not authenticated.');
        }

        $token = $user->currentAccessToken();
        $scope = $token ? TokenScope::fromAbilities($token->abilities ?? []) : null;

        // Fail closed: a missing/unrecognized ability must never grant
        // family-wide access.
        return $scope ?? TokenScope::Self;
    }

    public static function isFamily(Request $request): bool
    {
        return self::resolve($request) === TokenScope::Family;
    }
}
