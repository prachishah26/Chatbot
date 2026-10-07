---
paths:
  - '**'
---

# Structure

Read this before creating, moving or editing any file. If a change does not fit the map below, stop and ask instead of inventing a new place for it. When you add, move or delete a file, update this map in the same change.

## What the app is

A ChatGPT-style chatbot (brief: `task.md`). Laravel 13 + Blade + vanilla JS + Tailwind 4, SQLite. Replies come from a self-hosted **Ollama** model (default) or Google **Gemini**. Guests chat with session-scoped history; accounts are optional and carry history across browsers.

## Request flow

```
routes/web.php
  → Http/Requests/*Request        validation + typed accessors
  → Http/Controllers/*Controller     thin: transport only, one resource each
      → Chat/Conversations/ConversationService   persistence, always owner-scoped
      → Chat/Contracts/ChatProvider            (bound to Chat/Llm/ProviderRouter)
            → Chat/Llm/{Ollama,Gemini}/*ChatProvider
                  → Chat/Llm/ModelPool::firstAnswer()  model fallback
                  → Chat/Llm/LlmHttpClient::post()      HTTP + retries
  → Http/Responses/ApiResponse     JSON envelope, or a Blade view
```

## Map

| Path | Holds | Rule |
| --- | --- | --- |
| `app/Chat/Contracts/` | `ChatProvider` interface | The only type controllers depend on for replies. |
| `app/Chat/Exceptions/` | `ChatProviderException` | Every upstream failure. Messages are for logs only. |
| `app/Chat/Data/` | `ChatTurn`, `Role`, `ChatOwner` | Immutable, provider-neutral value objects. No HTTP, no vendor formats. |
| `app/Chat/Conversations/` | `ConversationService`, `GuestConversationService` | All reads/writes of `Conversation`/`Message` and chat session keys. |
| `app/Chat/Llm/` | Shared LLM plumbing: `ProviderRouter`, `ProviderDefinition`, `ModelPool`, `ModelChoice`, `ModelSelectionService`, `LlmHttpClient`, `ConfigValues` | Provider-agnostic. Never import a vendor class from here. |
| `app/Chat/Llm/Ollama/` | `OllamaChatProvider`, `OllamaConfig`, `OllamaWarmer` | Everything Ollama-specific, including its wire format. |
| `app/Chat/Llm/Gemini/` | `GeminiChatProvider`, `GeminiConfig` | Everything Gemini-specific, including its wire format. |
| `app/Chat/Presentation/` | `ConversationTimeline`, `PromptSuggestions` | Pure helpers that shape data for views. No DB or HTTP. |
| `app/Http/Controllers/` | `ChatController` (page), `MessageController`, `ConversationController`, `ModelSelectionController`, `Auth/*` | One resource per controller, resource verbs only (`index`, `show`, `store`, `destroy`). No business rules, no `response()->json()`. |
| `app/Http/Requests/` | FormRequests | All input validation lives here. |
| `app/Http/Responses/` | `ApiResponse` | The only way to build JSON responses. |
| `app/Models/` | `User`, `Conversation`, `Message` | Eloquent only; query scopes such as `ownedBy()`. |
| `app/Providers/ChatServiceProvider.php` | All chat container bindings | The only file that lists which providers exist. |
| `app/Providers/AppServiceProvider.php` | Rate limiters | Nothing chat-specific. |
| `app/Console/Commands/` | `chat:warm` | Resolve collaborators from the container; never `new` them. |
| `config/chatbot.php` | Every chat setting, all from `env()` | Read config only through here; never call `env()` elsewhere. |
| `resources/views/chat/`, `layouts/`, `auth/`, `components/` | Blade | Partials under `chat/partials/`; icons as `components/icon-*.blade.php`. |
| `resources/js/chat/` | ES modules, one concern per file | `index.js` wires everything; `api.js` is the only file that calls `fetch`. |
| `resources/css/app.css` | Tailwind 4 theme tokens | Use semantic tokens (`bg-panel`, `text-muted`), not raw shades. |
| `database/migrations/`, `database/factories/` | Schema and factories | Every model has a factory. |
| `tests/Unit/`, `tests/Feature/` | PHPUnit, flat, one `<Class>Test.php` per class | See `.ai/rules/testing.md`. |

## Naming conventions

| Kind | Pattern | Examples |
| --- | --- | --- |
| Class a controller delegates to (logic, session state) | `<Noun>Service` — never `Store`, `Manager`, `Helper` | `ConversationService`, `ModelSelectionService` |
| Controller | `<Resource>Controller`, resource verbs only (`index`, `show`, `store`, `update`, `destroy`). A new non-resource action means a new controller. | `MessageController@store` |
| FormRequest | `Store<Resource>Request` / `Update<Resource>Request`; auth keeps Breeze names | `StoreMessageRequest`, `LoginRequest` |
| Route name | `chat.<resource-plural>.<verb>`; singular for a singleton resource | `chat.messages.store`, `chat.conversations.destroy`, `chat.model.store` |
| Reusable query | Eloquent scope on the model, `scope<Name>` | `Conversation::scopeOwnedBy`, `scopeForSidebar` |
| LLM provider | `<Vendor>ChatProvider`, `<Vendor>Config` in `app/Chat/Llm/<Vendor>/` | `OllamaChatProvider` |
| Injected property | Named for its role, not its type | `$conversations`, `$modelSelection`, `$client` |
| Test | `<ClassUnderTest>Test`, methods in snake_case sentences | `ConversationTimelineTest` |
| Constant / enum case / config key | `UPPER_SNAKE` / `TitleCase` / `snake_case` | `PROVIDER_ERROR`, `Role::Assistant`, `history_limit` |
| Blade partial / JS module | kebab-case / lowercase | `thread-row.blade.php`, `sidebar.js` |

Say **conversation** in PHP class, method and variable names (it matches the `Conversation` model). "Thread" and "chat" appear only in UI copy and Blade variables.

## Adding things (where it goes)

- **New LLM provider** → `app/Chat/Llm/<Name>/` + a config block + one arm in `ChatServiceProvider::definition()`. Full steps: `.claude/skills/add-chat-provider/SKILL.md`.
- **New chat endpoint** → route in `routes/web.php` with a `throttle:` limiter, a FormRequest, a controller method, and an `ApiResponse` if it returns JSON.
- **New value object** → `app/Chat/Data/` as a `final readonly class`.
- **New view helper** → `app/Chat/Presentation/`.
- **New front-end behaviour** → a new module in `resources/js/chat/`, initialised from `index.js`.

## Do not

- Do not recreate `app/Chat/Support/` or `app/Chat/Providers/`. They were split up on purpose; `App\Providers` is for Laravel service providers only.
- Do not add top-level folders under `app/` or the project root without asking.
- Do not commit local tool state (`/tmp`, `/.marscode`, IDE folders, `database/database.sqlite`).
