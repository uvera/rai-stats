<?php

namespace Tests\Unit\Support;

use App\Enums\TokenScope;
use App\Models\User;
use App\Support\McpTokenScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Mcp\Request;
use Tests\TestCase;

class McpTokenScopeTest extends TestCase
{
    use RefreshDatabase;

    private function requestAs(User $user, ?array $abilities): Request
    {
        if ($abilities !== null) {
            $token = $user->createToken('t', $abilities)->accessToken;
            $user->withAccessToken($token);
        }

        $this->actingAs($user);

        return new Request;
    }

    public function test_resolves_to_self_userid_for_a_self_scoped_token(): void
    {
        $user = User::factory()->create();
        $request = $this->requestAs($user, [TokenScope::Self->ability()]);

        $this->assertSame(TokenScope::Self, McpTokenScope::resolve($request));
        $this->assertSame($user->id, McpTokenScope::resolveUserId($request));
        $this->assertFalse(McpTokenScope::isFamily($request));
    }

    public function test_resolves_to_null_userid_for_a_family_scoped_token(): void
    {
        $user = User::factory()->create();
        $request = $this->requestAs($user, [TokenScope::Family->ability()]);

        $this->assertSame(TokenScope::Family, McpTokenScope::resolve($request));
        $this->assertNull(McpTokenScope::resolveUserId($request));
        $this->assertTrue(McpTokenScope::isFamily($request));
    }

    public function test_fails_closed_to_self_scope_when_abilities_are_unrecognized(): void
    {
        $user = User::factory()->create();
        $request = $this->requestAs($user, ['some-other-ability']);

        $this->assertSame(TokenScope::Self, McpTokenScope::resolve($request));
        $this->assertSame($user->id, McpTokenScope::resolveUserId($request));
    }

    public function test_fails_closed_to_self_scope_when_there_is_no_access_token(): void
    {
        $user = User::factory()->create();
        $request = $this->requestAs($user, null);

        $this->assertSame(TokenScope::Self, McpTokenScope::resolve($request));
        $this->assertSame($user->id, McpTokenScope::resolveUserId($request));
    }

    public function test_throws_when_the_request_is_not_authenticated(): void
    {
        $this->expectException(\RuntimeException::class);

        McpTokenScope::resolve(new Request);
    }
}
