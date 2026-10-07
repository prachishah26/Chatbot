<?php

declare(strict_types=1);

namespace App\Providers;

use App\Chat\Contracts\ChatProvider;
use App\Chat\Conversations\ConversationService;
use App\Chat\Conversations\GuestConversationService;
use App\Chat\Llm\ConfigValues;
use App\Chat\Llm\Gemini\GeminiChatProvider;
use App\Chat\Llm\Gemini\GeminiConfig;
use App\Chat\Llm\ModelPool;
use App\Chat\Llm\ModelSelectionService;
use App\Chat\Llm\Ollama\OllamaChatProvider;
use App\Chat\Llm\Ollama\OllamaConfig;
use App\Chat\Llm\Ollama\OllamaWarmer;
use App\Chat\Llm\ProviderDefinition;
use App\Chat\Llm\ProviderRouter;
use Closure;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

/**
 * Wires the chat domain into the container.
 *
 * This is the only place that knows which LLM providers exist. Adding one
 * means a new App\Chat\Llm\<Name> folder, a config block in config/chatbot.php,
 * and one arm in definition() below — nothing else changes.
 */
final class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatProvider::class, fn (): ChatProvider => new ProviderRouter(
            array_map(
                static fn (ProviderDefinition $definition): Closure => $definition->make(...),
                $this->offeredProviders(),
            ),
            $this->defaultProvider(),
        ));

        $this->app->singleton(ModelSelectionService::class, fn (): ModelSelectionService => new ModelSelectionService(
            array_merge(...array_values(array_map(
                static fn (ProviderDefinition $definition): array => $definition->choices(),
                $this->offeredProviders(),
            ))),
        ));

        $this->app->singleton(ConversationService::class, fn (): ConversationService => new ConversationService(
            $this->app->make(AuthFactory::class),
            $this->app->make(GuestConversationService::class),
            (int) config('chatbot.history_limit', 20),
        ));

        $this->app->bind(OllamaWarmer::class, fn (): OllamaWarmer => new OllamaWarmer(
            $this->app->make(HttpFactory::class),
            $this->ollamaConfig(),
        ));
    }

    private function defaultProvider(): string
    {
        return (string) config('chatbot.provider', OllamaChatProvider::NAME);
    }

    /**
     * Providers the visitor may switch between, keyed by name, the default first.
     *
     * A hosted provider without credentials is left out rather than offered as
     * a choice that can only fail. The default is always kept, so a missing key
     * shows up as a logged error rather than an app that cannot boot.
     *
     * A provider that will not be offered is never parsed, so a half-finished
     * Gemini block cannot take down an Ollama-only install.
     *
     * @return array<string, ProviderDefinition>
     */
    private function offeredProviders(): array
    {
        $default = $this->defaultProvider();
        $names = array_unique([$default, ...ConfigValues::list(config('chatbot.available_providers', 'ollama,gemini'))]);
        $offered = [];

        foreach ($names as $name) {
            if ($name !== $default && ! $this->hasCredentials($name)) {
                continue;
            }

            $definition = $this->definition($name);

            if ($definition === null && $name === $default) {
                throw new RuntimeException("Unsupported chat provider [{$name}].");
            }

            if ($definition !== null) {
                $offered[$name] = $definition;
            }
        }

        return $offered;
    }

    /**
     * A cheap pre-check on the raw config, done before any settings are validated.
     */
    private function hasCredentials(string $name): bool
    {
        return match ($name) {
            GeminiChatProvider::NAME => ConfigValues::optionalString(config('chatbot.providers.gemini.key')) !== null,
            default => true,
        };
    }

    private function definition(string $name): ?ProviderDefinition
    {
        return match ($name) {
            OllamaChatProvider::NAME => $this->ollama(),
            GeminiChatProvider::NAME => $this->gemini(),
            default => null,
        };
    }

    /**
     * The self-hosted provider. Local models have no quota to exhaust, so its
     * pool never parks a model; it only puts the visitor's chosen model first.
     */
    private function ollama(): ProviderDefinition
    {
        $config = $this->ollamaConfig();

        return new ProviderDefinition(
            name: OllamaChatProvider::NAME,
            label: 'Ollama',
            isLocal: true,
            isUsable: true,
            models: $config->models,
            factory: fn (): ChatProvider => new OllamaChatProvider(
                $this->app->make(HttpFactory::class),
                $config,
                new ModelPool($this->app->make(Cache::class), $config->models, 0),
                $this->systemPrompt(),
            ),
        );
    }

    private function gemini(): ProviderDefinition
    {
        $config = GeminiConfig::fromArray((array) config('chatbot.providers.gemini', []));

        return new ProviderDefinition(
            name: GeminiChatProvider::NAME,
            label: 'Gemini',
            isLocal: false,
            isUsable: $config->hasKey(),
            models: $config->models,
            factory: fn (): ChatProvider => new GeminiChatProvider(
                $this->app->make(HttpFactory::class),
                $config,
                new ModelPool($this->app->make(Cache::class), $config->models, $config->cooldownSeconds),
                $this->systemPrompt(),
            ),
        );
    }

    private function ollamaConfig(): OllamaConfig
    {
        return OllamaConfig::fromArray((array) config('chatbot.providers.ollama', []));
    }

    private function systemPrompt(): string
    {
        return (string) config('chatbot.system_prompt', '');
    }
}
