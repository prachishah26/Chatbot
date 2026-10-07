<?php

declare(strict_types=1);

namespace App\Chat\Llm\Gemini;

use App\Chat\Contracts\ChatProvider;
use App\Chat\Data\ChatTurn;
use App\Chat\Data\Role;
use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Llm\LlmHttpClient;
use App\Chat\Llm\ModelPool;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Talks to Google's Gemini generateContent endpoint.
 *
 * Free API keys are available at https://aistudio.google.com/apikey. The free
 * tier both sheds load under contention and caps requests per model per day, so
 * transient failures are retried and an exhausted model falls back to the next.
 */
final readonly class GeminiChatProvider implements ChatProvider
{
    public const NAME = 'gemini';

    private LlmHttpClient $client;

    public function __construct(
        HttpFactory $http,
        private GeminiConfig $config,
        private ModelPool $models,
        private string $systemPrompt,
    ) {
        $this->client = new LlmHttpClient(
            $http,
            self::NAME,
            $config->timeout,
            $config->maxAttempts,
            $config->retryDelayMs,
            $config->hasKey() ? ['x-goog-api-key' => (string) $config->key] : [],
        );
    }

    public function reply(string $message, array $history = [], ?string $model = null): string
    {
        if (! $this->config->hasKey()) {
            throw ChatProviderException::missingCredentials(self::NAME);
        }

        $payload = $this->buildPayload($message, $history);

        return $this->models->firstAnswer(
            self::NAME,
            $model,
            fn (string $candidate): string => $this->extractText($this->client->post($this->urlFor($candidate), $payload)),
        );
    }

    private function urlFor(string $model): string
    {
        return sprintf('%s/models/%s:generateContent', rtrim($this->config->baseUrl, '/'), rawurlencode($model));
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
            static fn (ChatTurn $turn): array => self::toContent($turn),
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
     * Gemini names the assistant role "model".
     *
     * @return array{role: string, parts: list<array{text: string}>}
     */
    private static function toContent(ChatTurn $turn): array
    {
        return [
            'role' => $turn->role === Role::Assistant ? 'model' : 'user',
            'parts' => [['text' => $turn->text]],
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
