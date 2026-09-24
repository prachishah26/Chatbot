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
 * Talks to a self-hosted Ollama server's /api/chat endpoint.
 *
 * Install Ollama from https://ollama.com and pull a model (for example
 * `ollama pull llama3.2`). No API key is involved and no text leaves the
 * machine. A model that has not been pulled answers 404, so the next
 * configured model is tried instead.
 */
final readonly class OllamaChatProvider implements ChatProvider
{
    private const NAME = 'ollama';

    /**
     * Statuses that indicate a transient server problem worth retrying.
     */
    private const RETRYABLE_STATUSES = [500, 502, 503, 504];

    public function __construct(
        private HttpFactory $http,
        private OllamaConfig $config,
        private ModelPool $models,
        private string $systemPrompt,
    ) {}

    public function reply(string $message, array $history = [], ?string $model = null): string
    {
        $messages = $this->buildMessages($message, $history);
        $failure = ChatProviderException::unreachable(self::NAME, 'No model was available to try.');

        foreach ($this->models->ordered($model) as $candidate) {
            try {
                return $this->extractText($this->sendToModel($candidate, $messages));
            } catch (ChatProviderException $e) {
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
     * @param  list<array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    private function sendToModel(string $model, array $messages): array
    {
        $url = rtrim($this->config->baseUrl, '/').'/api/chat';
        $payload = $this->buildPayload($model, $messages);
        $attempts = $this->config->maxAttempts;
        $failure = ChatProviderException::unreachable(self::NAME, 'No request was attempted.');

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->post($url, $payload);
            } catch (ConnectionException $e) {
                $failure = ChatProviderException::unreachable(self::NAME, $e->getMessage().' Is `ollama serve` running?');
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
            ->acceptJson()
            ->asJson()
            ->post($url, $payload);
    }

    private function pauseBefore(int $next, int $attempts): void
    {
        if ($next > $attempts || $this->config->retryDelayMs === 0) {
            return;
        }

        usleep($this->config->retryDelayMs * 1000 * ($next - 1));
    }

    /**
     * Builds the message list without mutating the caller's history.
     *
     * @param  list<ChatTurn>  $history
     * @return list<array{role: string, content: string}>
     */
    private function buildMessages(string $message, array $history): array
    {
        $turns = array_map(
            static fn (ChatTurn $turn): array => $turn->toOllamaMessage(),
            [...$history, ChatTurn::user($message)],
        );

        return trim($this->systemPrompt) === ''
            ? array_values($turns)
            : [['role' => 'system', 'content' => $this->systemPrompt], ...$turns];
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @return array<string, mixed>
     */
    private function buildPayload(string $model, array $messages): array
    {
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'stream' => false,
            'options' => [
                'temperature' => $this->config->temperature,
                'num_predict' => $this->config->maxOutputTokens,
            ],
        ];

        if ($this->config->keepAlive !== null) {
            $payload['keep_alive'] = $this->config->keepAlive;
        }

        if ($this->config->think !== null) {
            $payload['think'] = $this->config->think;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractText(array $response): string
    {
        $content = data_get($response, 'message.content');
        $text = is_string($content) ? trim($content) : '';

        if ($text === '') {
            $reason = (string) data_get($response, 'done_reason', '');

            throw ChatProviderException::emptyReply(self::NAME, $reason === '' ? '' : "Done reason: {$reason}.");
        }

        return $text;
    }
}
