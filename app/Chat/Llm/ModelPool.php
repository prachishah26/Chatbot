<?php

declare(strict_types=1);

namespace App\Chat\Llm;

use App\Chat\Exceptions\ChatProviderException;
use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * Chooses which of a provider's models to ask next.
 *
 * Hosted free tiers (Gemini) count requests per model per day, so a model that
 * has run out is parked for a cooldown and the next one is tried instead.
 * Parked models are still returned last, so the pool never reports itself as
 * empty. A pool with a zero cooldown (Ollama) never parks anything and only
 * puts the visitor's chosen model first.
 */
final readonly class ModelPool
{
    private const CACHE_PREFIX = 'chat.model.exhausted:';

    /**
     * @param  list<string>  $models  Preferred model first.
     */
    public function __construct(
        private Cache $cache,
        private array $models,
        private int $cooldownSeconds,
    ) {}

    /**
     * Asks each model in turn, starting with $preferred, until one answers.
     *
     * Only failures that another model could avoid (missing model, exhausted
     * quota) move on to the next model; anything else is rethrown at once.
     *
     * @template TAnswer
     *
     * @param  Closure(string): TAnswer  $ask  Receives the model name.
     * @return TAnswer
     *
     * @throws ChatProviderException The last failure when no model answers.
     */
    public function firstAnswer(string $provider, ?string $preferred, Closure $ask): mixed
    {
        $failure = ChatProviderException::unreachable($provider, 'No model was available to try.');

        foreach ($this->ordered($preferred) as $model) {
            try {
                return $ask($model);
            } catch (ChatProviderException $e) {
                if ($e->isQuotaExhausted()) {
                    $this->park($model);
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
     * Every model, ready ones first.
     *
     * A preferred model is asked first, unless it is parked for exhausted quota
     * — then it waits its turn with the other cooling models rather than
     * spending a round trip that is known to fail.
     *
     * @return list<string>
     */
    public function ordered(?string $preferred = null): array
    {
        $ready = [];
        $cooling = [];

        foreach ($this->models as $model) {
            if ($this->cache->has(self::key($model))) {
                $cooling[] = $model;

                continue;
            }

            if ($model === $preferred) {
                array_unshift($ready, $model);

                continue;
            }

            $ready[] = $model;
        }

        return [...$ready, ...$cooling];
    }

    /**
     * Parks a model that has just reported an exhausted quota.
     */
    public function park(string $model): void
    {
        if ($this->cooldownSeconds > 0) {
            $this->cache->put(self::key($model), true, $this->cooldownSeconds);
        }
    }

    private static function key(string $model): string
    {
        return self::CACHE_PREFIX.$model;
    }
}
