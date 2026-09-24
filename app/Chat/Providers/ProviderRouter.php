<?php

declare(strict_types=1);

namespace App\Chat\Providers;

use App\Chat\Contracts\ChatProvider;
use App\Chat\Support\ModelChoice;
use Closure;
use InvalidArgumentException;

/**
 * Sends each message to whichever provider the chosen model belongs to.
 *
 * Model ids arrive as "provider/model" (see ModelChoice). Providers are built
 * on first use, so a visitor who only ever chats locally never constructs the
 * hosted client.
 */
final class ProviderRouter implements ChatProvider
{
    /** @var array<string, ChatProvider> */
    private array $resolved = [];

    /**
     * @param  array<string, Closure(): ChatProvider>  $factories  Keyed by provider name.
     */
    public function __construct(
        private readonly array $factories,
        private readonly string $defaultProvider,
    ) {
        if (! isset($factories[$defaultProvider])) {
            throw new InvalidArgumentException("The default chat provider [{$defaultProvider}] is not available.");
        }
    }

    public function reply(string $message, array $history = [], ?string $model = null): string
    {
        [$provider, $providerModel] = $this->route($model);

        return $this->provider($provider)->reply($message, $history, $providerModel);
    }

    /**
     * An unknown or missing id goes to the default provider with its default model.
     *
     * @return array{0: string, 1: ?string}
     */
    private function route(?string $model): array
    {
        $parsed = $model === null ? null : ModelChoice::parse($model);

        if ($parsed === null || ! isset($this->factories[$parsed[0]])) {
            return [$this->defaultProvider, null];
        }

        return $parsed;
    }

    private function provider(string $name): ChatProvider
    {
        return $this->resolved[$name] ??= ($this->factories[$name])();
    }
}
