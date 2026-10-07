---
paths:
  - 'app/Chat/**'
  - app/Providers/ChatServiceProvider.php
  - config/chatbot.php
---

# Chat Domain

## Providers go through the contract

Controllers and commands depend on `App\Chat\Contracts\ChatProvider` (or on a class bound in `ChatServiceProvider`), never on a concrete provider. `ProviderRouter` picks the backend from the model id.

## Model ids are "provider/model"

A model id is always `<provider>/<model>` (see `ModelChoice::parse`, which splits on the first `/` only, because Ollama names can contain `/`). Only ids listed by `ModelSelectionService` can be chosen. Never pass a raw client-supplied model name upstream.

## One HTTP path for every provider

Providers call `LlmHttpClient::post()` for HTTP and `ModelPool::firstAnswer()` for model fallback. Do not add another retry loop, `usleep`, or `Http::` call inside a provider. Retry on 5xx and connection errors; fall back to the next model on 404 or 429 (`ChatProviderException::suggestsAnotherModel`).

## Wire formats stay inside their provider folder

Mapping `ChatTurn` to a vendor payload (Gemini's `model` role, Ollama's `messages`) is private to that provider. Code in `app/Chat/Data`, `app/Chat/Contracts` and the root of `app/Chat/Llm` must not import, construct or branch on a vendor. Naming one in a doc comment as an example is fine.

## Upstream errors never reach the visitor

`ChatProviderException` messages can contain response bodies. Log them server-side and show the fixed `MessageController::PROVIDER_ERROR` text. Never echo an exception message into JSON or a view.

## Never mutate history

`ChatProvider::reply()` must not modify the `$history` it receives. Build new arrays (`[...$history, ChatTurn::user($message)]`).

## Every conversation query is owner-scoped

Load threads only through `ConversationService::find()` / `sidebar()` / `current()`, which apply `ChatOwner::constrain()`. Never use `Conversation::find()` or route-model binding on a raw id: the public ULID in the URL is not proof of ownership.

## Session keys are a public contract

`chat.conversation_id`, `chat.owner_key`, `chat.claimable_ids` and `chat.model` persist in live sessions and are asserted in tests. Do not rename them.

## Configuration

Settings are read from `config('chatbot.*')` and turned into a validated `*Config` object via `fromArray()`, using `ConfigValues` for list, string and bool parsing. Secrets (`GEMINI_API_KEY`) come from `.env` only. Hosted endpoints must be `https://`.
