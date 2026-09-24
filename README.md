# Samvaad

Samvaad is a chat app built with Laravel 13, Blade and Tailwind CSS. By default it runs on an open-source model through [Ollama](https://ollama.com), right on your own machine, so there's no API key, no quota and no bill. Google's Gemini API is also supported, and visitors can switch between the two in the model picker.

Each visitor gets their own conversations, and the recent messages are sent back to the model with every new question. That's what lets follow-ups work: ask "What is RAG?", then "What are its main components?", and the second question is understood as part of the same thread. You don't need an account to use it. Guests get threads tied to their session, and if you sign up later, those threads come with you so your history is there on any browser.

## What it does

- Multi-turn chat, with a system prompt and history length you can configure
- Runs locally with Ollama by default. Your messages never leave the machine
- A model picker grouped by provider: **Ollama (Local)** and **Gemini (Cloud)**. Only models listed in config can be chosen, and your pick is remembered for the session. Gemini only appears once its API key is set
- The local model is loaded into memory when the dev server starts, so the first message isn't slowed down by loading it
- A sidebar of threads you can create, switch between and delete. Each one is named after its first message
- Falls back through a list of Gemini models. If one runs out of free-tier quota it's set aside for a while and the next one answers instead
- Retries when a request fails for a temporary reason, and shows a normal error message instead of a stack trace if the model is unavailable
- Optional sign-up and login, with rate limits on the chat, UI and auth routes
- Replies render Markdown and have a copy button

## Before you start

You'll need:

- PHP 8.3 or newer (this was built on 8.5) with the `sqlite3` and `pdo_sqlite` extensions
- Composer 2
- Node.js 20 or newer, and npm
- [Ollama](https://ollama.com/download) with at least one model pulled
- *Optional:* a Gemini API key, free from [Google AI Studio](https://aistudio.google.com/apikey), if you also want the cloud models

### Installing Ollama

```bash
# Linux (macOS and Windows installers are on https://ollama.com/download)
curl -fsSL https://ollama.com/install.sh | sh

# Download the default model (about 2 GB)
ollama pull llama3.2
```

On Linux the installer runs Ollama as a background service. Check it's up with `ollama list`, or start it yourself with `ollama serve`.

## Setup

```bash
git clone <repository-url> ai-chatbot
cd ai-chatbot

composer install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate

npm install
npm run build
```

With Ollama running and `llama3.2` pulled, that's all you need. The defaults in `.env.example` point at `http://localhost:11434`.

To also offer Gemini, paste your key into `.env`:

```env
GEMINI_API_KEY=your-key-here
```

## Running it

```bash
composer run dev
```

That one command first loads the default Ollama model into memory (`php artisan chat:warm`), then runs the PHP server, the queue worker, log tailing and Vite together. Then open http://localhost:8000.

If you'd rather not run the asset watcher, run `npm run build` once and use `php artisan serve` instead. You can run `php artisan chat:warm` yourself any time to load the model before chatting.

### About speed

On a machine without a GPU, a small model like `llama3.2` writes about 10 tokens (roughly 7 words) per second. A short reply takes about a second and a long one can take much longer. After `OLLAMA_KEEP_ALIVE` of inactivity the model is unloaded from memory, and the next message waits a few seconds while it loads again. Set `OLLAMA_KEEP_ALIVE=-1` to keep it loaded all the time. A smaller model such as `llama3.2:1b` is faster, though its answers are weaker.

## Settings

The chat settings live in `config/chatbot.php` and all of them read from environment variables.

### General

| Variable | Default | What it does |
| --- | --- | --- |
| `CHAT_PROVIDER` | `ollama` | The default provider for new visitors: `ollama` or `gemini` |
| `CHAT_PROVIDERS` | `ollama,gemini` | Providers offered in the model picker. Gemini is left out until `GEMINI_API_KEY` is set. Use `ollama` to keep everything local |
| `CHAT_SYSTEM_PROMPT` | friendly, concise assistant | Added to the start of every conversation |
| `CHAT_HISTORY_LIMIT` | `20` | How many past messages get sent along as context |
| `CHAT_MAX_MESSAGE_LENGTH` | `4000` | Longest message a user can send, in characters |

### Ollama

| Variable | Default | What it does |
| --- | --- | --- |
| `OLLAMA_BASE_URL` | `http://localhost:11434` | Where the Ollama server is listening |
| `OLLAMA_MODELS` | `llama3.2` | Models shown in the picker, default first. Pull each one with `ollama pull <name>` |
| `OLLAMA_TIMEOUT` | `120` | Request timeout, in seconds. Local generation is slower than a hosted API |
| `OLLAMA_KEEP_ALIVE` | `10m` | How long the model stays loaded in memory after a reply. `-1` keeps it loaded |
| `OLLAMA_TEMPERATURE` | `0.7` | Higher is more creative, lower is more predictable |
| `OLLAMA_MAX_OUTPUT_TOKENS` | `1024` | Longest reply, in tokens |
| `OLLAMA_THINK` | — | For reasoning models such as `qwen3` or `deepseek-r1` only: `true` or `false`. Leave blank for others |
| `OLLAMA_MAX_ATTEMPTS` | `2` | How many times to try when Ollama is busy or still loading |

### Gemini

| Variable | Default | What it does |
| --- | --- | --- |
| `GEMINI_API_KEY` | — | Your Gemini key. Without it, Gemini is hidden from the picker. It's only ever read from the environment, never committed |
| `GEMINI_MODELS` | `gemini-3.6-flash,gemini-3.5-flash-lite,gemini-3.1-flash-lite` | The fallback order, best model first. This list is also what the picker shows |
| `GEMINI_MODEL_COOLDOWN_MINUTES` | `30` | How long to skip a model after it runs out of quota |
| `GEMINI_THINKING_LEVEL` | `low` | How much Gemini 3.x thinks before replying. Higher is slower |
| `GEMINI_TIMEOUT` | `60` | Request timeout, in seconds |
| `GEMINI_MAX_ATTEMPTS` | `3` | How many times to retry a model before moving to the next one |

### Adding another provider

Write a class that implements `App\Chat\Contracts\ChatProvider`, add a `match` arm for it in `AppServiceProvider` (in `makeProvider()` and `modelsFor()`), give it a section under `providers` in `config/chatbot.php`, and add its name to `CHAT_PROVIDERS`. Picker choices are stored as `provider/model`, and `ProviderRouter` sends each message to the matching provider, so nothing else needs to change.

## Tests

```bash
composer test                       # everything
php artisan test --compact          # same thing, shorter output
php artisan test --filter=ChatTest  # just one file
```

The feature tests cover the chat routes, switching between Ollama and Gemini in the picker, auth, the `chat:warm` command, and handing a guest's threads over at sign-up. The unit tests cover the Ollama and Gemini providers, the provider router, the model preference and fallback pool, prompt suggestions and how a thread is built for display. None of them make real network calls.

## Formatting

```bash
vendor/bin/pint --dirty
```

## Where things live

```
app/Chat/                  Chat logic: conversation store, model preference, small helper types
app/Chat/Contracts/        The ChatProvider interface every provider implements
app/Chat/Providers/        Ollama and Gemini clients, their config objects, the provider router,
                           the model warmer and the Gemini fallback pool
app/Console/Commands/      The chat:warm command
app/Http/Controllers/      Chat and auth controllers
app/Http/Requests/         Validation for incoming requests
config/chatbot.php         Provider, prompt, history and starter-prompt settings
resources/views/chat/      The Blade chat UI
resources/js/chat/         Front-end pieces (api, markdown, sidebar, menus)
tests/                     PHPUnit feature and unit tests
```
