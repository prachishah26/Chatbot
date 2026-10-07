<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Contracts\ChatProvider;
use App\Chat\Llm\ProviderRouter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProviderRouterTest extends TestCase
{
    #[Test]
    public function it_sends_the_message_to_the_chosen_provider_with_the_bare_model(): void
    {
        $router = $this->router();

        $this->assertSame('gemini answered with gemini-3.6-flash', $router->reply('Hi', [], 'gemini/gemini-3.6-flash'));
        $this->assertSame('ollama answered with qwen3:8b', $router->reply('Hi', [], 'ollama/qwen3:8b'));
    }

    #[Test]
    public function it_uses_the_default_provider_and_model_without_a_choice(): void
    {
        $this->assertSame('ollama answered with default', $this->router()->reply('Hi'));
    }

    #[Test]
    public function it_ignores_a_provider_that_is_not_available(): void
    {
        $this->assertSame('ollama answered with default', $this->router()->reply('Hi', [], 'openai/gpt-5'));
    }

    #[Test]
    public function it_builds_a_provider_only_when_it_is_first_used(): void
    {
        $built = [];

        $router = new ProviderRouter([
            'ollama' => function () use (&$built): ChatProvider {
                $built[] = 'ollama';

                return $this->echoing('ollama');
            },
            'gemini' => function () use (&$built): ChatProvider {
                $built[] = 'gemini';

                return $this->echoing('gemini');
            },
        ], 'ollama');

        $router->reply('one');
        $router->reply('two');

        $this->assertSame(['ollama'], $built, 'Gemini is never built, and Ollama only once.');
    }

    #[Test]
    public function it_requires_the_default_provider_to_be_available(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ProviderRouter(['gemini' => fn (): ChatProvider => $this->echoing('gemini')], 'ollama');
    }

    private function router(): ProviderRouter
    {
        return new ProviderRouter([
            'ollama' => fn (): ChatProvider => $this->echoing('ollama'),
            'gemini' => fn (): ChatProvider => $this->echoing('gemini'),
        ], 'ollama');
    }

    private function echoing(string $name): ChatProvider
    {
        return new readonly class($name) implements ChatProvider
        {
            public function __construct(private string $name) {}

            public function reply(string $message, array $history = [], ?string $model = null): string
            {
                return "{$this->name} answered with ".($model ?? 'default');
            }
        };
    }
}
