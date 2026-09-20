<?php

declare(strict_types=1);

namespace App\Providers;

use App\Chat\Contracts\ChatProvider;
use App\Chat\ConversationStore;
use App\Chat\ModelPreference;
use App\Chat\Providers\GeminiChatProvider;
use App\Chat\Providers\GeminiConfig;
use App\Chat\Providers\ModelPool;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use RuntimeException;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatProvider::class, function (): ChatProvider {
            $name = (string) config('chatbot.provider', 'gemini');

            return match ($name) {
                'gemini' => $this->gemini(),
                default => throw new RuntimeException("Unsupported chat provider [{$name}]."),
            };
        });

        $this->app->singleton(
            ConversationStore::class,
            fn (): ConversationStore => new ConversationStore((int) config('chatbot.history_limit', 20)),
        );

        $this->app->singleton(ModelPreference::class, fn (): ModelPreference => new ModelPreference(
            GeminiConfig::fromArray((array) config('chatbot.providers.gemini', []))->models,
        ));
    }

    /**
     * Builds the Gemini provider with its validated settings and model pool.
     */
    private function gemini(): GeminiChatProvider
    {
        $settings = (array) config('chatbot.providers.gemini', []);
        $config = GeminiConfig::fromArray($settings);

        return new GeminiChatProvider(
            $this->app->make(HttpFactory::class),
            $config,
            new ModelPool(
                $this->app->make(Cache::class),
                $config->models,
                ((int) ($settings['model_cooldown_minutes'] ?? 30)) * 60,
            ),
            (string) config('chatbot.system_prompt', ''),
        );
    }

    public function boot(): void
    {
        $this->registerChatRateLimiter();
    }

    /**
     * Caps how often a single visitor can call the AI provider.
     */
    private function registerChatRateLimiter(): void
    {
        RateLimiter::for('chat', static fn (Request $request): Limit => Limit::perMinute(20)
            ->by((string) $request->ip())
            ->response(static fn (): JsonResponse => response()->json([
                'success' => false,
                'data' => null,
                'error' => 'You are sending messages too quickly. Please wait a moment.',
            ], 429)));

        // Opening, clearing and deleting threads costs nothing upstream, so the
        // limit here only needs to stop runaway clients.
        RateLimiter::for('chat-ui', static fn (Request $request): Limit => Limit::perMinute(60)
            ->by((string) $request->ip()));

        /*
         | Sign-in and sign-up are the credential-guessing surface. Keying on the
         | submitted address as well as the IP slows an attacker working through
         | one account without locking out everyone behind a shared address.
         */
        RateLimiter::for('auth', static function (Request $request): array {
            $email = Str::lower((string) $request->input('email'));

            return [
                Limit::perMinute(5)->by($email.'|'.$request->ip()),

                // A backstop the attacker cannot shed by rotating source addresses.
                Limit::perHour(30)->by('auth-account:'.$email),
            ];
        });
    }
}
