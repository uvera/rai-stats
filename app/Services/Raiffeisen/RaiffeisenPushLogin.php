<?php

namespace App\Services\Raiffeisen;

use App\Services\Raiffeisen\Data\PushLoginResult;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Support\Facades\Log;

/**
 * The mobile push 2FA handshake. It shares the client's cookie jar: the SSE
 * connect writes Set-Cookie back into that jar so the later Guzzle start
 * and send calls carry them.
 */
class RaiffeisenPushLogin
{
    private const ORIGIN = 'https://rol.raiffeisenbank.rs';

    private const REFERER = self::ORIGIN.'/Retail/Home/Login';

    private const SIGNALR_BASE_URL = self::ORIGIN.'/Retail/signalr';

    private const SIGNALR_PROTOCOL = '2.1';

    private const IBANKING_HUB_DATA = '[{"name":"ibankinghub"}]';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:132.0) Gecko/20100101 Firefox/132.0';

    /**
     * @param  callable(string, string, array, string): RawSseStream  $sseStreamFactory
     */
    public function __construct(
        private readonly Client $http,
        private readonly CookieJar $cookieJar,
        private $sseStreamFactory,
    ) {}

    /**
     * Runs the SignalR long-poll handshake and waits (up to $timeoutSeconds)
     * for the user to approve the push notification on their phone.
     */
    public function requestLoginPush(string $ticket, string $username, int $timeoutSeconds = 180): PushLoginResult
    {
        $token = $this->signalRNegotiate();
        $this->trace('raiffeisen.signalr.negotiate.ok', ['token_prefix' => substr($token, 0, 12)]);

        $stream = $this->signalRConnectStream($token);
        $this->trace('raiffeisen.signalr.connect.opened');

        $deadline = microtime(true) + $timeoutSeconds;
        $buffer = '';

        // Pulls the next complete "data: ..." SSE line, buffering any partial
        // trailing line across reads. Drains what's already buffered before
        // touching the stream again - a line read into $buffer while looking
        // for one thing (the ready signal) must still be seen when looking
        // for the next thing (the push result), even if the stream itself
        // has no more bytes left to give by then.
        $nextDataLine = function () use ($stream, &$buffer, $deadline): ?string {
            while (true) {
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);

                    if (str_starts_with($line, 'data: ')) {
                        return substr($line, strlen('data: '));
                    }

                    if (trim($line) !== '') {
                        $this->trace('raiffeisen.signalr.line.ignored', ['line' => $line]);
                    }
                }

                if ($stream->eof()) {
                    return null;
                }

                if (microtime(true) > $deadline) {
                    throw new RaiffeisenException('Timed out reading SignalR stream');
                }

                $chunk = $stream->read(4096);
                if ($chunk === '') {
                    // Nothing available yet on a still-open stream; avoid a
                    // tight busy-loop while waiting for the next server push.
                    usleep(100_000);
                }
                $buffer .= $chunk;
            }
        };

        while (true) {
            $payload = $nextDataLine();
            if ($payload === null) {
                throw new RaiffeisenException('SignalR stream closed before becoming ready');
            }

            $this->trace('raiffeisen.signalr.ready.candidate', ['payload' => $payload]);

            if (trim($payload) === 'initialized') {
                break;
            }

            $envelope = json_decode($payload, true);
            if (is_array($envelope) && ($envelope['S'] ?? null) === 1) {
                break;
            }
        }

        $this->trace('raiffeisen.signalr.ready');

        $startResponse = $this->signalRStart($token);
        $this->trace('raiffeisen.signalr.start.response', ['status' => $startResponse->getStatusCode(), 'body' => (string) $startResponse->getBody()]);

        $sendResponse = $this->signalRSend($token, $ticket, $username);
        $this->trace('raiffeisen.signalr.send.response', ['status' => $sendResponse->getStatusCode(), 'body' => (string) $sendResponse->getBody()]);

        while (true) {
            $payload = $nextDataLine();
            if ($payload === null) {
                throw new RaiffeisenException('SignalR stream closed before push was approved');
            }

            if (trim($payload) === '' || trim($payload) === 'initialized') {
                continue;
            }

            $envelope = json_decode($payload, true);
            if (! is_array($envelope)) {
                $this->trace('raiffeisen.signalr.push.line.unparsed', ['payload' => $payload]);

                continue;
            }

            $this->trace('raiffeisen.signalr.push.line', ['envelope' => $envelope]);

            if (! empty($envelope['E'])) {
                throw new RaiffeisenException("SignalR hub error: {$envelope['E']}");
            }

            foreach ($envelope['M'] ?? [] as $invocation) {
                if (strcasecmp($invocation['M'] ?? '', 'LoginUPRequestApproved') !== 0) {
                    continue;
                }

                $args = $invocation['A'] ?? [];
                if (empty($args)) {
                    throw new RaiffeisenException('LoginUPRequestApproved with no args');
                }

                $result = PushLoginResult::fromArray($args[0]);

                if (! $result->isApproved()) {
                    throw new RaiffeisenException("Login push {$result->status}");
                }

                return $result;
            }
        }
    }

    public function loginUPPush(string $firstStepTicket, string $pushRequestContent, int $sessionId): void
    {
        $response = $this->http->post(
            self::ORIGIN.'/Retail/Protected/Services/RetailLoginService.svc/LoginUPPush',
            [
                'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
                'json' => [
                    'firstStepTicket' => $firstStepTicket,
                    'pushRequestContent' => $pushRequestContent,
                    'sessionID' => $sessionId,
                ],
            ]
        );

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("Unexpected LoginUPPush status {$response->getStatusCode()}.");
        }
    }

    /**
     * SignalR handshake tracing. These payloads carry the connection token,
     * the pre-2FA ticket and raw Set-Cookie headers, so they are silenced
     * unless RAIFFEISEN_TRACE is explicitly enabled to debug a login.
     *
     * @param  array<string, mixed>  $context
     */
    private function trace(string $event, array $context = []): void
    {
        if (config('services.raiffeisen.trace')) {
            Log::debug($event, $context);
        }
    }

    private function signalRNegotiate(): string
    {
        $url = self::SIGNALR_BASE_URL.'/negotiate?'.http_build_query([
            'clientProtocol' => self::SIGNALR_PROTOCOL,
            'connectionData' => self::IBANKING_HUB_DATA,
            '_' => (int) (microtime(true) * 1000),
        ]);

        $response = $this->http->get($url, [
            'headers' => ['Origin' => self::ORIGIN, 'Referer' => self::REFERER],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("negotiate status {$response->getStatusCode()}.");
        }

        $data = json_decode($response->getBody()->getContents(), true);
        $this->trace('raiffeisen.signalr.negotiate.body', ['body' => $data]);
        $token = $data['ConnectionToken'] ?? '';

        if ($token === '') {
            throw new RaiffeisenException('empty connection token');
        }

        return $token;
    }

    /**
     * Deliberately bypasses Guzzle here - see RawSseStream's docblock for
     * why. Every other call in this client goes through Guzzle as normal;
     * only this one long-lived SSE connection needs the raw socket.
     */
    private function signalRConnectStream(string $token)
    {
        $host = parse_url(self::ORIGIN, PHP_URL_HOST);
        $path = '/Retail/signalr/connect?'.http_build_query([
            'transport' => 'serverSentEvents',
            'clientProtocol' => self::SIGNALR_PROTOCOL,
            'connectionToken' => $token,
            'connectionData' => self::IBANKING_HUB_DATA,
            'tid' => random_int(0, 9),
        ]);

        $dummyRequest = $this->cookieJar->withCookieHeader(new Psr7Request('GET', self::ORIGIN.$path));

        $stream = ($this->sseStreamFactory)(
            $host,
            $path,
            [
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Origin' => self::ORIGIN,
                'Referer' => self::REFERER,
            ],
            $dummyRequest->getHeaderLine('Cookie'),
        );

        $this->trace('raiffeisen.signalr.connect.response', [
            'status' => $stream->statusCode,
            'headers' => $stream->responseHeaders,
        ]);

        if ($stream->statusCode !== 200) {
            throw new RaiffeisenException("connect status {$stream->statusCode}");
        }

        // Feed any cookies this connection set back into the shared jar so
        // the subsequent Guzzle-based start/send calls carry them too.
        foreach ($stream->setCookieHeaders as $rawSetCookie) {
            $cookie = SetCookie::fromString($rawSetCookie);
            if ($cookie->getDomain() === null) {
                $cookie->setDomain($host);
            }
            $this->cookieJar->setCookie($cookie);
        }

        return $stream;
    }

    private function signalRStart(string $token)
    {
        $url = self::SIGNALR_BASE_URL.'/start?'.http_build_query([
            'transport' => 'serverSentEvents',
            'clientProtocol' => self::SIGNALR_PROTOCOL,
            'connectionToken' => $token,
            'connectionData' => self::IBANKING_HUB_DATA,
            '_' => (int) (microtime(true) * 1000),
        ]);

        $response = $this->http->get($url, [
            'headers' => ['Origin' => self::ORIGIN, 'Referer' => self::REFERER],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("start status {$response->getStatusCode()}.");
        }

        return $response;
    }

    private function signalRSend(string $token, string $ticket, string $username)
    {
        $data = json_encode([
            'H' => 'ibankinghub',
            'M' => 'CreateLoginPushRequest',
            'A' => [$ticket, $username],
            'I' => 0,
        ]);

        $url = self::SIGNALR_BASE_URL.'/send?'.http_build_query([
            'transport' => 'serverSentEvents',
            'clientProtocol' => self::SIGNALR_PROTOCOL,
            'connectionToken' => $token,
            'connectionData' => self::IBANKING_HUB_DATA,
        ]);

        $response = $this->http->post($url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                'Origin' => self::ORIGIN,
                'Referer' => self::REFERER,
            ],
            'form_params' => ['data' => $data],
        ]);

        if ($response->getStatusCode() !== 200) {
            throw new RaiffeisenException("send status {$response->getStatusCode()}.");
        }

        return $response;
    }
}
