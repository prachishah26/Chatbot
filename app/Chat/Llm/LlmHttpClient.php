<?php

declare(strict_types=1);

namespace App\Chat\Llm;

use App\Chat\Exceptions\ChatProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Posts JSON to an LLM API, retrying transient failures with a linear backoff.
 *
 * Shared by every provider so retry and error-reporting behaviour stays the
 * same no matter which backend answers. Every failure surfaces as a
 * ChatProviderException carrying the upstream status, which is what the
 * model-fallback logic in ModelPool keys on.
 */
final readonly class LlmHttpClient
{
    /**
     * Statuses that indicate a transient upstream problem worth retrying.
     */
    private const RETRYABLE_STATUSES = [500, 502, 503, 504];

    /**
     * @param  string  $provider  Provider name used in exception messages.
     * @param  array<string, string>  $headers  Sent with every request (e.g. an API key).
     * @param  string  $connectionHint  Appended to "unreachable" errors to help whoever reads the log.
     */
    public function __construct(
        private HttpFactory $http,
        private string $provider,
        private int $timeout,
        private int $maxAttempts = 1,
        private int $retryDelayMs = 0,
        private array $headers = [],
        private string $connectionHint = '',
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> The decoded response body.
     *
     * @throws ChatProviderException When every attempt fails or a non-retryable status comes back.
     */
    public function post(string $url, array $payload): array
    {
        $failure = ChatProviderException::unreachable($this->provider, 'No request was attempted.');

        for ($attempt = 1; $attempt <= $this->maxAttempts; $attempt++) {
            try {
                $response = $this->send($url, $payload);
            } catch (ConnectionException $e) {
                // A timeout mid-generation is indistinguishable from a stalled queue, so it is retried too.
                $failure = ChatProviderException::unreachable(
                    $this->provider,
                    trim($e->getMessage().' '.$this->connectionHint),
                );
                $this->pauseBefore($attempt + 1);

                continue;
            }

            if ($response->successful()) {
                return (array) $response->json();
            }

            $failure = ChatProviderException::requestFailed($this->provider, $response->status(), $response->body());

            if (! in_array($response->status(), self::RETRYABLE_STATUSES, true)) {
                throw $failure;
            }

            $this->pauseBefore($attempt + 1);
        }

        throw $failure;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ConnectionException
     */
    private function send(string $url, array $payload): Response
    {
        return $this->http
            ->timeout($this->timeout)
            ->withHeaders($this->headers)
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);
    }

    /**
     * Sleeps before attempt $next only when that attempt will actually happen.
     */
    private function pauseBefore(int $next): void
    {
        if ($next > $this->maxAttempts || $this->retryDelayMs === 0) {
            return;
        }

        usleep($this->retryDelayMs * 1000 * ($next - 1));
    }
}
