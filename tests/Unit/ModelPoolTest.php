<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Llm\ModelPool;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModelPoolTest extends TestCase
{
    private const MODELS = ['gemini-3.6-flash', 'gemini-3.5-flash-lite', 'gemini-3.1-flash-lite'];

    #[Test]
    public function it_asks_the_preferred_model_first(): void
    {
        $this->assertSame(
            ['gemini-3.1-flash-lite', 'gemini-3.6-flash', 'gemini-3.5-flash-lite'],
            $this->pool()->ordered('gemini-3.1-flash-lite'),
        );
    }

    #[Test]
    public function it_keeps_config_order_without_a_preference(): void
    {
        $this->assertSame(self::MODELS, $this->pool()->ordered());
    }

    #[Test]
    public function it_does_not_hoist_a_model_that_is_out_of_quota(): void
    {
        $pool = $this->pool();
        $pool->park('gemini-3.1-flash-lite');

        $order = $pool->ordered('gemini-3.1-flash-lite');

        $this->assertSame('gemini-3.6-flash', $order[0], 'A parked favourite does not waste the first attempt.');
        $this->assertSame('gemini-3.1-flash-lite', end($order), 'It is still tried, just last.');
    }

    #[Test]
    public function it_ignores_a_preference_it_does_not_hold(): void
    {
        $this->assertSame(self::MODELS, $this->pool()->ordered('something-else'));
    }

    #[Test]
    public function a_zero_cooldown_never_parks_a_model(): void
    {
        $pool = $this->pool(cooldownSeconds: 0);
        $pool->park('gemini-3.6-flash');

        $this->assertSame(self::MODELS, $pool->ordered());
    }

    #[Test]
    public function first_answer_returns_the_first_successful_reply(): void
    {
        $asked = [];

        $answer = $this->pool()->firstAnswer('gemini', 'gemini-3.5-flash-lite', function (string $model) use (&$asked): string {
            $asked[] = $model;

            return "reply from {$model}";
        });

        $this->assertSame('reply from gemini-3.5-flash-lite', $answer);
        $this->assertSame(['gemini-3.5-flash-lite'], $asked);
    }

    #[Test]
    public function first_answer_moves_on_and_parks_a_model_that_is_out_of_quota(): void
    {
        $pool = $this->pool();
        $asked = [];

        $answer = $pool->firstAnswer('gemini', null, function (string $model) use (&$asked): string {
            $asked[] = $model;

            if ($model === 'gemini-3.6-flash') {
                throw ChatProviderException::requestFailed('gemini', 429, 'quota');
            }

            return 'ok';
        });

        $this->assertSame('ok', $answer);
        $this->assertSame(['gemini-3.6-flash', 'gemini-3.5-flash-lite'], $asked);
        $this->assertSame('gemini-3.6-flash', $pool->ordered()[2], 'The exhausted model now goes last.');
    }

    #[Test]
    public function first_answer_moves_on_from_a_missing_model_without_parking_it(): void
    {
        $pool = $this->pool();

        $pool->firstAnswer('ollama', null, static function (string $model): string {
            if ($model === 'gemini-3.6-flash') {
                throw ChatProviderException::requestFailed('ollama', 404, 'not found');
            }

            return 'ok';
        });

        $this->assertSame(self::MODELS, $pool->ordered());
    }

    #[Test]
    public function first_answer_rethrows_a_failure_another_model_cannot_fix(): void
    {
        $asked = 0;

        try {
            $this->pool()->firstAnswer('gemini', null, static function () use (&$asked): string {
                $asked++;

                throw ChatProviderException::requestFailed('gemini', 400, 'bad request');
            });
            $this->fail('The exception should have been rethrown.');
        } catch (ChatProviderException $e) {
            $this->assertSame(400, $e->status);
        }

        $this->assertSame(1, $asked);
    }

    #[Test]
    public function first_answer_throws_the_last_failure_when_every_model_fails(): void
    {
        $this->expectException(ChatProviderException::class);
        $this->expectExceptionMessage('HTTP 404');

        $this->pool()->firstAnswer('ollama', null, static function (string $model): string {
            throw ChatProviderException::requestFailed('ollama', 404, "{$model} not found");
        });
    }

    private function pool(int $cooldownSeconds = 1800): ModelPool
    {
        return new ModelPool(new CacheRepository(new ArrayStore), self::MODELS, $cooldownSeconds);
    }
}
