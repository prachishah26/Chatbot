<?php

declare(strict_types=1);

namespace App\Chat\Providers;

use App\Chat\Exceptions\ChatProviderException;
use Illuminate\Http\Client\ConnectionException;
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
    private const NAME = 'ollama';

    public function __construct(
        private HttpFactory $http,
        private OllamaConfig $config,
    ) {}

    /**
     * The model a visitor gets unless they pick another.
     */
    public function defaultModel(): string
    {
        return $this->config->models[0];
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

        try {
            $response = $this->http
                ->timeout($this->config->timeout)
                ->acceptJson()
                ->asJson()
                ->post(rtrim($this->config->baseUrl, '/').'/api/generate', $payload);
        } catch (ConnectionException $e) {
            throw ChatProviderException::unreachable(self::NAME, $e->getMessage());
        }

        if (! $response->successful()) {
            throw ChatProviderException::requestFailed(self::NAME, $response->status(), $response->body());
        }
    }
}
