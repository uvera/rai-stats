<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RaiffeisenLoginJob;
use App\Services\Raiffeisen\Data\AccountBalance;
use App\Services\Raiffeisen\Data\LoginResult;
use App\Services\Raiffeisen\Data\PushLoginResult;
use App\Services\Raiffeisen\RaiffeisenClient;
use App\Support\RaiffeisenImportSession;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class RaiffeisenLoginJobTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_login_without_2fa_reaches_ready_state_with_accounts_and_cookies(): void
    {
        $sessionId = RaiffeisenImportSession::start(1);
        RaiffeisenImportSession::setPassword($sessionId, 'super-secret');

        $account = new AccountBalance('12345', 'Test account', 'RSD', '941', '33', 1000, 900, null, null);

        $client = Mockery::mock(RaiffeisenClient::class);
        $client->shouldReceive('login')->once();
        $client->shouldReceive('loginFont')->once()->with('testuser', 'super-secret')
            ->andReturn(new LoginResult('ticket', 'reqtok', false, 1, 2));
        $client->shouldReceive('allAccountBalance')->once()->andReturn([$account]);
        $client->shouldReceive('exportCookies')->once()->andReturn(['cookie' => 'value']);
        $client->shouldNotReceive('requestLoginPush');

        $this->app->instance(RaiffeisenClient::class, $client);

        (new RaiffeisenLoginJob($sessionId, 'testuser'))->handle($client);

        $state = RaiffeisenImportSession::getState($sessionId);

        $this->assertSame('ready', $state['status']);
        $this->assertSame(['cookie' => 'value'], $state['cookies']);
        $this->assertCount(1, $state['accounts']);

        // The password must be gone from cache after the job reads it.
        $this->assertNull(RaiffeisenImportSession::takePassword($sessionId));
    }

    public function test_login_with_2fa_waits_for_push_then_reaches_ready(): void
    {
        $sessionId = RaiffeisenImportSession::start(1);
        RaiffeisenImportSession::setPassword($sessionId, 'super-secret');

        $client = Mockery::mock(RaiffeisenClient::class);
        $client->shouldReceive('login')->once();
        $client->shouldReceive('loginFont')->once()
            ->andReturn(new LoginResult('ticket', 'reqtok', true, 1, 2));
        $client->shouldReceive('requestLoginPush')->once()->with('ticket', 'testuser', 180)
            ->andReturn(new PushLoginResult('APPROVED', 'req-1', 'ticket', 'push-content'));
        $client->shouldReceive('loginUPPush')->once()->with('ticket', 'push-content', 2);
        $client->shouldReceive('allAccountBalance')->once()->andReturn([]);
        $client->shouldReceive('exportCookies')->once()->andReturn([]);

        (new RaiffeisenLoginJob($sessionId, 'testuser'))->handle($client);

        $this->assertSame('ready', RaiffeisenImportSession::getState($sessionId)['status']);
    }

    public function test_failure_is_recorded_and_password_still_consumed(): void
    {
        $sessionId = RaiffeisenImportSession::start(1);
        RaiffeisenImportSession::setPassword($sessionId, 'super-secret');

        $client = Mockery::mock(RaiffeisenClient::class);
        $client->shouldReceive('login')->once();
        $client->shouldReceive('loginFont')->once()->andThrow(new \RuntimeException('bad credentials'));

        (new RaiffeisenLoginJob($sessionId, 'testuser'))->handle($client);

        $state = RaiffeisenImportSession::getState($sessionId);
        $this->assertSame('failed', $state['status']);
        $this->assertSame('bad credentials', $state['message']);
        $this->assertNull(RaiffeisenImportSession::takePassword($sessionId));
    }

    public function test_missing_password_fails_without_calling_the_client(): void
    {
        $sessionId = RaiffeisenImportSession::start(1);
        // Note: no setPassword() call - simulates the cache key already expired.

        $client = Mockery::mock(RaiffeisenClient::class);
        $client->shouldNotReceive('login');

        (new RaiffeisenLoginJob($sessionId, 'testuser'))->handle($client);

        $this->assertSame('failed', RaiffeisenImportSession::getState($sessionId)['status']);
    }
}
