<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Chat\Contracts\ChatProvider;
use App\Chat\Llm\ModelChoice;
use App\Chat\Llm\ProviderDefinition;
use Closure;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProviderDefinitionTest extends TestCase
{
    #[Test]
    public function it_builds_the_client_only_when_asked(): void
    {
        $built = 0;
        $definition = $this->definition(function () use (&$built): ChatProvider {
            $built++;

            return $this->stubProvider();
        });

        $this->assertSame(0, $built);
        $definition->make();
        $this->assertSame(1, $built);
    }

    #[Test]
    public function its_choices_carry_the_provider_label_and_locality(): void
    {
        $choices = $this->definition(fn (): ChatProvider => $this->stubProvider())->choices();

        $this->assertSame(['local/tiny', 'local/big:7b'], array_map(static fn (ModelChoice $choice): string => $choice->id, $choices));
        $this->assertSame('Local LLM', $choices[0]->providerLabel());
        $this->assertTrue($choices[1]->isLocal());
    }

    /**
     * @param  Closure(): ChatProvider  $factory
     */
    private function definition(Closure $factory): ProviderDefinition
    {
        return new ProviderDefinition('local', 'Local LLM', true, true, ['tiny', 'big:7b'], $factory);
    }

    private function stubProvider(): ChatProvider
    {
        return new class implements ChatProvider
        {
            public function reply(string $message, array $history = [], ?string $model = null): string
            {
                return 'ok';
            }
        };
    }
}
