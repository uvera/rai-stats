<?php

namespace Tests\Feature\Filament;

use App\Enums\TokenScope;
use App\Filament\Pages\McpTokens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class McpTokensPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_a_token_and_see_the_plaintext_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(McpTokens::class)
            ->callAction('createToken', data: [
                'name' => 'Claude Desktop',
                'scope' => TokenScope::Self->value,
            ])
            ->assertSet('plainTextToken', fn (?string $token) => filled($token));

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'Claude Desktop',
        ]);

        $token = $user->tokens()->where('name', 'Claude Desktop')->sole();
        $this->assertSame([TokenScope::Self->ability()], $token->abilities);
    }

    public function test_user_can_revoke_their_own_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('to-revoke', [TokenScope::Self->ability()]);
        $this->actingAs($user);

        Livewire::test(McpTokens::class)
            ->callTableAction('revoke', $token->accessToken);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->accessToken->id]);
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $token = $owner->createToken('owned-by-other-user', [TokenScope::Self->ability()]);

        $this->actingAs($other);

        Livewire::test(McpTokens::class)
            ->assertCanNotSeeTableRecords([$token->accessToken]);

        $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->accessToken->id]);
    }
}
