<?php

declare(strict_types=1);

namespace App\Chat\Llm;

use App\Chat\Contracts\ChatProvider;
use Closure;

/**
 * Everything the app needs to know about one configured provider.
 *
 * Built once per provider in ChatServiceProvider. The client itself is only
 * constructed on demand, so a visitor who only ever chats locally never pays
 * for building the hosted client.
 */
final readonly class ProviderDefinition
{
    /**
     * @param  string  $name  Config key, also the prefix of every model id ("gemini/…").
     * @param  string  $label  Human name shown in the model picker.
     * @param  bool  $isLocal  Whether prompts stay on this machine.
     * @param  bool  $isUsable  False when required credentials are missing.
     * @param  list<string>  $models  Preferred model first.
     * @param  Closure(): ChatProvider  $factory
     */
    public function __construct(
        public string $name,
        public string $label,
        public bool $isLocal,
        public bool $isUsable,
        public array $models,
        private Closure $factory,
    ) {}

    public function make(): ChatProvider
    {
        return ($this->factory)();
    }

    /**
     * @return list<ModelChoice>
     */
    public function choices(): array
    {
        return ModelChoice::listFor($this->name, $this->models, $this->label, $this->isLocal);
    }
}
