<?php

namespace App\Services\Raiffeisen;

/**
 * A minimal raw TLS-socket HTTP/1.1 client for the one connection Guzzle's
 * StreamHandler couldn't read progressively: RaiOnline's SignalR SSE
 * /connect endpoint returns neither Content-Length nor Transfer-Encoding
 * (relying purely on the connection staying open), and against the real
 * bank - behind Cloudflare - PHP's stream-wrapper-backed HTTP client never
 * surfaced a single byte from it, timeout or not, even with keep-alive
 * forced. Go's http.Transport (used by the original, working Go client)
 * reads directly off the socket and has no such issue. This does the same:
 * open the socket ourselves, write the request by hand, and read whatever
 * bytes are available as they arrive - no HTTP client abstraction in the
 * way that might be buffering underneath.
 *
 * Exposes just read()/eof(), the same shape RaiffeisenClient's SSE line
 * reader already expects from a Guzzle stream, so it drops in as a
 * replacement with no change to the calling code.
 */
class RawSseStream
{
    /** @var resource */
    private $socket;

    private bool $headersParsed = false;

    private bool $eof = false;

    public int $statusCode = 0;

    /** @var array<string, string> */
    public array $responseHeaders = [];

    /** @var string[] Raw Set-Cookie header values, in case there are several. */
    public array $setCookieHeaders = [];

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        string $host,
        string $path,
        array $headers,
        string $cookieHeader,
        int $connectTimeoutSeconds = 10,
    ) {
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);

        $socket = stream_socket_client(
            "ssl://{$host}:443",
            $errno,
            $errstr,
            $connectTimeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if ($socket === false) {
            throw new RaiffeisenException("Failed to open TLS socket to {$host}: {$errstr} ({$errno})");
        }

        $this->socket = $socket;
        stream_set_blocking($this->socket, true);
        stream_set_timeout($this->socket, 30);

        $requestLines = ["GET {$path} HTTP/1.1", "Host: {$host}"];
        foreach ($headers as $name => $value) {
            $requestLines[] = "{$name}: {$value}";
        }
        if ($cookieHeader !== '') {
            $requestLines[] = "Cookie: {$cookieHeader}";
        }
        $requestLines[] = 'Connection: keep-alive';
        $requestLines[] = "\r\n";

        fwrite($this->socket, implode("\r\n", $requestLines));

        $this->parseResponseHeaders();
    }

    private function parseResponseHeaders(): void
    {
        $statusLine = fgets($this->socket);
        if ($statusLine === false || ! preg_match('#^HTTP/\d\.\d\s+(\d+)#', $statusLine, $m)) {
            throw new RaiffeisenException('Failed to read HTTP status line from raw SSE socket');
        }
        $this->statusCode = (int) $m[1];

        while (($line = fgets($this->socket)) !== false) {
            $line = rtrim($line, "\r\n");
            if ($line === '') {
                break;
            }
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $name = trim($name);
            $value = trim($value);

            if (strcasecmp($name, 'Set-Cookie') === 0) {
                $this->setCookieHeaders[] = $value;
            } else {
                $this->responseHeaders[$name] = $value;
            }
        }

        $this->headersParsed = true;
    }

    public function read(int $length): string
    {
        if (! $this->headersParsed || $this->eof) {
            return '';
        }

        $chunk = fread($this->socket, $length);

        // A per-call read timeout (stream_set_timeout above) is not the same
        // as the connection closing - an SSE stream can sit idle between
        // events for a long time. Only a genuine feof() means the socket is
        // actually gone; a timed-out read just means "nothing yet," and the
        // caller's own overall deadline decides whether to keep waiting.
        if ($chunk === false || feof($this->socket)) {
            $this->eof = true;
        }

        return $chunk === false ? '' : $chunk;
    }

    public function eof(): bool
    {
        return $this->eof;
    }

    public function __destruct()
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
        }
    }
}
