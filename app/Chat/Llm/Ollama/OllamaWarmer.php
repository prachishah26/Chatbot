<?php

declare(strict_types=1);

namespace App\Chat\Llm\Ollama;

use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Llm\LlmHttpClient;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Loads an Ollama model into memory ahead of the first chat message.
 *
 * Reading a model from disk takes several seconds on a CPU-only machine, and
 * without this the first visitor pays for it. Ollama treats a generate request
 * with no prompt as "load and keep resident", so no tokens are produced.
 */
final readonly class OllamaWarmer
{
    private LlmHttpClient $client;

    public function __construct(
        HttpFactory $http,
        private OllamaConfig $config,
    ) {
        $this->client = new LlmHttpClient($http, OllamaChatProvider::NAME, $config->timeout);
    }

    /**
     * The model a visitor gets unless they pick another.
     */
    public function defaultModel(): string
    {
        return $this->config->defaultModel();
    }

    /**
     * @throws ChatProviderException When the server is down or the model is missing.
     */
    public function warm(): void
    {
        $payload = ['model' => $this->defaultModel()];

        if ($this->config->keepAlive !== null) {
            $payload['keep_alive'] = $this->config->keepAlive;
        }

        $this->client->post($this->config->url('/api/generate'), $payload);
    }
}
