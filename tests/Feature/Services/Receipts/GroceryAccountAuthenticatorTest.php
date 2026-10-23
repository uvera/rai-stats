<?php

namespace Tests\Feature\Services\Receipts;

use App\Models\GroceryAccount;
use App\Services\Receipts\Contracts\ProviderClient;
use App\Services\Receipts\Data\ProviderToken;
use App\Services\Receipts\GroceryAccountAuthenticator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class GroceryAccountAuthenticatorTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    public function test_returns_a_live_token_without_calling_the_provider(): void
    {
        $account = GroceryAccount::factory()->metro()->withValidToken()->create([
            'refresh_token' => 'stored-refresh',
        ]);
        $client = Mockery::mock(ProviderClient::class);
        $client->shouldNotReceive('refresh');
        $client->shouldNotReceive('login');

        $token = app(GroceryAccountAuthenticator::class)->unattended($account, $client);

        $this->assertSame($account->access_token, $token->accessToken);
        $this->assertSame('stored-refresh', $token->refreshToken);
    }

    public function test_persists_a_refreshed_token(): void
    {
        $account = GroceryAccount::factory()->metro()->create([
            'access_token' => null,
            'refresh_token' => 'old-refresh',
        ]);
        $fresh = ProviderToken::fromAccessToken($this->jwt(), 'new-refresh');
        $client = Mockery::mock(ProviderClient::class);
        $client->shouldReceive('refresh')->once()->with($account)->andReturn($fresh);
        $client->shouldNotReceive('login');

        $token = app(GroceryAccountAuthenticator::class)->unattended($account, $client);

        $this->assertSame($fresh->accessToken, $token->accessToken);
        $this->assertSame('new-refresh', $account->fresh()->refresh_token);
        $this->assertNotNull($account->fresh()->access_token);
    }

    public function test_logs_in_with_the_stored_password_when_refresh_returns_null(): void
    {
        $account = GroceryAccount::factory()->metro()->withPassword('stored-secret')->create([
            'access_token' => null,
            'refresh_token' => null,
        ]);
        $fresh = ProviderToken::fromAccessToken($this->jwt(), 'from-password');
        $client = Mockery::mock(ProviderClient::class);
        $client->shouldReceive('refresh')->once()->andReturn(null);
        $client->shouldReceive('login')->once()->with($account, 'stored-secret')->andReturn($fresh);

        app(GroceryAccountAuthenticator::class)->unattended($account, $client);

        $this->assertSame('from-password', $account->fresh()->refresh_token);
    }

    public function test_returns_null_when_nothing_can_sign_in(): void
    {
        $account = GroceryAccount::factory()->metro()->create([
            'access_token' => null,
            'refresh_token' => null,
            'password' => null,
        ]);
        $client = Mockery::mock(ProviderClient::class);
        $client->shouldReceive('refresh')->once()->andReturn(null);
        $client->shouldNotReceive('login');

        $this->assertNull(app(GroceryAccountAuthenticator::class)->unattended($account, $client));
    }

    private function jwt(): string
    {
        $payload = rtrim(strtr(base64_encode(json_encode(['exp' => now()->addHour()->timestamp])), '+/', '-_'), '=');

        return 'h.'.$payload.'.s';
    }
}
