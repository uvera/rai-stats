<?php

namespace Tests\Feature\Mcp;

use App\Enums\TokenScope;
use App\Mcp\Servers\RaiStatsServer;
use App\Mcp\Tools\GetLeaderboardTool;
use App\Mcp\Tools\ListAccountsTool;
use App\Mcp\Tools\ListTransactionsTool;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RaiStatsServerToolsTest extends TestCase
{
    use RefreshDatabase;

    private function userWithToken(User $user, TokenScope $scope): User
    {
        $token = $user->createToken('test-token', [$scope->ability()])->accessToken;

        return $user->withAccessToken($token);
    }

    public function test_self_scoped_token_only_sees_its_own_accounts(): void
    {
        $me = User::factory()->create();
        $sibling = User::factory()->create();
        Account::factory()->for($me)->create(['number' => 'mine']);
        Account::factory()->for($sibling)->create(['number' => 'theirs']);

        $response = RaiStatsServer::actingAs($this->userWithToken($me, TokenScope::Self))
            ->tool(ListAccountsTool::class);

        $response->assertOk()->assertStructuredContent(
            fn (AssertableJson $json) => $json->has('accounts', 1)->where('accounts.0.number', 'mine')
        );
    }

    public function test_family_scoped_token_sees_every_users_accounts(): void
    {
        $me = User::factory()->create();
        $sibling = User::factory()->create();
        Account::factory()->for($me)->create(['number' => 'mine']);
        Account::factory()->for($sibling)->create(['number' => 'theirs']);

        $response = RaiStatsServer::actingAs($this->userWithToken($me, TokenScope::Family))
            ->tool(ListAccountsTool::class);

        $response->assertOk()->assertStructuredContent(
            fn (AssertableJson $json) => $json->has('accounts', 2)->where(
                'accounts',
                fn ($accounts) => $accounts->pluck('number')->sort()->values()->all() === ['mine', 'theirs']
            )
        );
    }

    public function test_self_scoped_token_only_sees_its_own_transactions(): void
    {
        $me = User::factory()->create();
        $sibling = User::factory()->create();
        $myAccount = Account::factory()->for($me)->create();
        $theirAccount = Account::factory()->for($sibling)->create();
        Transaction::factory()->for($myAccount)->for($me)->create(['place' => 'My Shop']);
        Transaction::factory()->for($theirAccount)->for($sibling)->create(['place' => 'Their Shop']);

        $response = RaiStatsServer::actingAs($this->userWithToken($me, TokenScope::Self))
            ->tool(ListTransactionsTool::class);

        $response->assertOk()->assertStructuredContent(
            fn (AssertableJson $json) => $json->has('transactions', 1)->where('transactions.0.place', 'My Shop')
        );
    }

    public function test_leaderboard_rejects_a_self_scoped_token(): void
    {
        $me = User::factory()->create();

        $response = RaiStatsServer::actingAs($this->userWithToken($me, TokenScope::Self))
            ->tool(GetLeaderboardTool::class);

        $response->assertHasErrors();
    }

    public function test_leaderboard_works_for_a_family_scoped_token(): void
    {
        $me = User::factory()->create();
        $account = Account::factory()->for($me)->create();
        Transaction::factory()->for($account)->for($me)->create(['amount_cents' => -1000, 'date' => now()]);

        $response = RaiStatsServer::actingAs($this->userWithToken($me, TokenScope::Family))
            ->tool(GetLeaderboardTool::class);

        $response->assertOk();
    }

    private function callListAccountsOverHttp(string $bearerToken): TestResponse
    {
        return $this->withHeaders(['Authorization' => "Bearer {$bearerToken}"])
            ->postJson('/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/call',
                'params' => ['name' => (new ListAccountsTool)->name(), 'arguments' => []],
            ]);
    }

    public function test_a_valid_token_can_call_tools_over_the_real_mcp_endpoint(): void
    {
        $me = User::factory()->create();
        $plainTextToken = $me->createToken('t', [TokenScope::Self->ability()])->plainTextToken;

        $this->callListAccountsOverHttp($plainTextToken)->assertOk();
    }

    public function test_a_revoked_token_can_no_longer_call_tools_over_the_real_mcp_endpoint(): void
    {
        $me = User::factory()->create();
        $newToken = $me->createToken('t', [TokenScope::Self->ability()]);
        $newToken->accessToken->delete();

        $this->callListAccountsOverHttp($newToken->plainTextToken)->assertUnauthorized();
    }
}
