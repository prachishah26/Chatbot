<?php

declare(strict_types=1);

namespace App\Chat\Llm\Ollama;

use App\Chat\Contracts\ChatProvider;
use App\Chat\Data\ChatTurn;
use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Llm\LlmHttpClient;
use App\Chat\Llm\ModelPool;
use Illuminate\Http\Client\Factory as HttpFactory;

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
    public const NAME = 'ollama';

    private LlmHttpClient $client;

    public function __construct(
        HttpFactory $http,
        private OllamaConfig $config,
        private ModelPool $models,
        private string $systemPrompt,
    ) {
        $this->client = new LlmHttpClient(
            $http,
            self::NAME,
            $config->timeout,
            $config->maxAttempts,
            $config->retryDelayMs,
            connectionHint: 'Is `ollama serve` running?',
        );
    }

    public function reply(string $message, array $history = [], ?string $model = null): string
    {
        $messages = $this->buildMessages($message, $history);
        $url = $this->config->url('/api/chat');

        return $this->models->firstAnswer(
            self::NAME,
            $model,
            fn (string $candidate): string => $this->extractText(
                $this->client->post($url, $this->buildPayload($candidate, $messages)),
            ),
        );
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
            static fn (ChatTurn $turn): array => ['role' => $turn->role->value, 'content' => $turn->text],
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
