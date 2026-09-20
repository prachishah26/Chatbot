<?php

declare(strict_types=1);

namespace App\Chat\Providers;

use App\Chat\Contracts\ChatProvider;
use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Support\ChatTurn;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Response;

/**
 * Talks to Google's Gemini generateContent endpoint.
 *
 * Free API keys are available at https://aistudio.google.com/apikey. The free
 * tier both sheds load under contention and caps requests per model per day, so
 * transient failures are retried and an exhausted model falls back to the next.
 */
final readonly class GeminiChatProvider implements ChatProvider
{
    private const NAME = 'gemini';

    /**
     * Statuses that indicate a transient upstream problem worth retrying.
     */
    private const RETRYABLE_STATUSES = [500, 502, 503, 504];

    public function __construct(
        private HttpFactory $http,
        private GeminiConfig $config,
        private ModelPool $models,
        private string $systemPrompt,
    ) {}

    public function reply(string $message, array $history = [], ?string $model = null): string
    {
        if ($this->config->key === null || $this->config->key === '') {
            throw ChatProviderException::missingCredentials(self::NAME);
        }

        $response = $this->send($this->buildPayload($message, $history), $model);

        return $this->extractText($response);
    }

    /**
     * Asks each model in turn until one answers, starting with $preferred.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function send(array $payload, ?string $preferred = null): array
    {
        $failure = ChatProviderException::unreachable(self::NAME, 'No model was available to try.');

        foreach ($this->models->ordered($preferred) as $model) {
            try {
                return $this->sendToModel($model, $payload);
            } catch (ChatProviderException $e) {
                if ($e->isQuotaExhausted()) {
                    $this->models->park($model);
                }

                if (! $e->suggestsAnotherModel()) {
                    throw $e;
                }

                $failure = $e;
            }
        }

        throw $failure;
    }

    /**
     * Posts to one model, retrying transient failures with a linear backoff.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sendToModel(string $model, array $payload): array
    {
        $url = sprintf(
            '%s/models/%s:generateContent',
            rtrim($this->config->baseUrl, '/'),
            rawurlencode($model),
        );

        $attempts = $this->config->maxAttempts;
        $failure = ChatProviderException::unreachable(self::NAME, 'No request was attempted.');

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->post($url, $payload);
            } catch (ConnectionException $e) {
                // A timeout mid-generation is indistinguishable from a stalled queue.
                $failure = ChatProviderException::unreachable(self::NAME, $e->getMessage());
                $this->pauseBefore($attempt + 1, $attempts);

                continue;
            }

            if ($response->successful()) {
                return (array) $response->json();
            }

            $failure = ChatProviderException::requestFailed(self::NAME, $response->status(), $response->body());

            if (! in_array($response->status(), self::RETRYABLE_STATUSES, true)) {
                throw $failure;
            }

            $this->pauseBefore($attempt + 1, $attempts);
        }

        throw $failure;
    }

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws ConnectionException
     */
    private function post(string $url, array $payload): Response
    {
        return $this->http
            ->timeout($this->config->timeout)
            ->withHeaders(['x-goog-api-key' => $this->config->key])
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);
    }

    /**
     * Sleeps before $next only when another attempt actually follows.
     */
    private function pauseBefore(int $next, int $attempts): void
    {
        if ($next > $attempts || $this->config->retryDelayMs === 0) {
            return;
        }

        usleep($this->config->retryDelayMs * 1000 * ($next - 1));
    }

    /**
     * Builds the request body without mutating the caller's history.
     *
     * @param  list<ChatTurn>  $history
     * @return array<string, mixed>
     */
    private function buildPayload(string $message, array $history): array
    {
        $contents = array_map(
            static fn (ChatTurn $turn): array => $turn->toGeminiContent(),
            [...$history, ChatTurn::user($message)],
        );

        $generationConfig = [
            'temperature' => $this->config->temperature,
            'maxOutputTokens' => $this->config->maxOutputTokens,
        ];

        if ($this->config->thinkingLevel !== null) {
            $generationConfig['thinkingConfig'] = ['thinkingLevel' => $this->config->thinkingLevel];
        }

        return [
            'systemInstruction' => ['parts' => [['text' => $this->systemPrompt]]],
            'contents' => array_values($contents),
            'generationConfig' => $generationConfig,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractText(array $response): string
    {
        $parts = data_get($response, 'candidates.0.content.parts', []);

        $text = trim(implode('', array_map(
            static fn (mixed $part): string => is_array($part) && is_string($part['text'] ?? null) ? $part['text'] : '',
            is_array($parts) ? $parts : [],
        )));

        if ($text === '') {
            // A blocked prompt or an exhausted token budget both land here.
            $reason = (string) data_get($response, 'candidates.0.finishReason', '')
                ?: (string) data_get($response, 'promptFeedback.blockReason', '');

            throw ChatProviderException::emptyReply(self::NAME, $reason === '' ? '' : "Finish reason: {$reason}.");
        }

        return $text;
    }
}
