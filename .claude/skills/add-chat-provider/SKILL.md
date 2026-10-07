---
name: add-chat-provider
description: Step-by-step recipe for adding a new LLM backend (OpenAI-compatible, Anthropic, Groq, LM Studio, etc.) to this chatbot. Use whenever the user asks to add, support, or integrate another model provider or API.
---

# Add a chat provider

Follow these steps in order. Use Ollama (`app/Chat/Llm/Ollama/`) as the reference implementation, or Gemini if the provider needs an API key. Read `.ai/rules/chat-domain.md` first.

Throughout, `<Name>` is the class prefix (e.g. `Groq`) and `<key>` is the lowercase config key (e.g. `groq`).

## 1. Config block

Add a `providers.<key>` block to `config/chatbot.php`. Every value comes from `env('<NAME>_…')`, and a secret has no default. Add matching commented entries to `.env.example`. If the provider should be selectable, add `<key>` to the `available_providers` default.

## 2. Config object: `app/Chat/Llm/<Name>/<Name>Config.php`

Scaffold with `php artisan make:class Chat/Llm/<Name>/<Name>Config --no-interaction`, then:
- Make it a `final readonly class` with constructor validation (at least one model; `https://` for hosted APIs; `maxAttempts >= 1`).
- Add `public static function fromArray(array $config): self` that parses values with `ConfigValues::list()` / `optionalString()` / `optionalBool()`.
- For a key-based API, add `hasKey(): bool`.

## 3. Provider: `app/Chat/Llm/<Name>/<Name>ChatProvider.php`

- Declare `final readonly class … implements ChatProvider` with `public const NAME = '<key>';`.
- Take the constructor `(HttpFactory $http, <Name>Config $config, ModelPool $models, string $systemPrompt)` and build an `LlmHttpClient` in it (auth headers go in `$headers`).
- In `reply()`, build the payload once, then `return $this->models->firstAnswer(self::NAME, $model, fn (string $candidate): string => $this->extractText($this->client->post(...)));`
- Map `ChatTurn` to the vendor format in a private method here. Never add `to<Vendor>()` to `ChatTurn` or `Role`.
- Throw `ChatProviderException::emptyReply()` when the reply text is blank, and `missingCredentials()` when the key is absent.
- Do not write retry loops, `usleep`, or `Http::` calls; `LlmHttpClient` handles those.

## 4. Register it: `app/Providers/ChatServiceProvider.php`

- Add `<Name>ChatProvider::NAME => $this-><key>(),` to `definition()`.
- Add a private `<key>(): ProviderDefinition` method returning `new ProviderDefinition(name, label, isLocal, isUsable, models, factory)`. Copy `gemini()` for a hosted API or `ollama()` for a local one. `ModelPool` cooldown: `0` for local, or `$config->cooldownSeconds` for quota-limited APIs.

No other file lists providers. If you find yourself editing a `match` elsewhere, stop: that is the old pattern.

## 5. Tests (write them first)

- `tests/Unit/<Name>ChatProviderTest.php`: copy the shape of `OllamaChatProviderTest`. Cover the payload sent (system prompt + history + message), reply extraction, empty reply, HTTP failure, 404/429 fallback to the next model, and missing key if relevant. Use `Http::fake()` and `retryDelayMs: 0`.
- Add `fromArray` cases to `tests/Unit/ProviderConfigTest.php`.
- Add a case to `tests/Feature/ChatServiceProviderTest.php` proving the provider is offered when usable and hidden when not.

## 6. Docs and finish

- Update `README.md` (setup + env vars) and the map in `.ai/rules/structure.md`.
- Run the `finish-change` skill.
