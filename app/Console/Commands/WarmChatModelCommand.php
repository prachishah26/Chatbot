<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Providers\OllamaConfig;
use App\Chat\Providers\OllamaWarmer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory as HttpFactory;

/**
 * Preloads the local chat model so the first message is not slowed by loading.
 *
 * Runs before `composer run dev` starts the server, so it always exits
 * successfully: a stopped Ollama should not stop the app from booting.
 */
#[Signature('chat:warm')]
#[Description('Load the default Ollama chat model into memory ahead of the first message')]
final class WarmChatModelCommand extends Command
{
    public function handle(HttpFactory $http): int
    {
        if (config('chatbot.provider') !== 'ollama') {
            $this->components->info('Nothing to warm: the active chat provider is hosted.');

            return self::SUCCESS;
        }

        $warmer = new OllamaWarmer($http, OllamaConfig::fromArray((array) config('chatbot.providers.ollama', [])));
        $model = $warmer->defaultModel();

        try {
            $this->components->task("Loading {$model} into memory", static fn () => $warmer->warm());
        } catch (ChatProviderException $e) {
            $this->components->warn("Could not preload {$model}. {$this->hint($e, $model)}");

            return self::SUCCESS;
        }

        $this->components->info("{$model} is loaded and ready.");

        return self::SUCCESS;
    }

    private function hint(ChatProviderException $e, string $model): string
    {
        return $e->status === 404
            ? "Download it first with `ollama pull {$model}`."
            : 'Is `ollama serve` running? The first message will be slower.';
    }
}
