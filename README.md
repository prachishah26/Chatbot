# Samvaad

Samvaad is a chat app built with Laravel 13, Blade and Tailwind CSS. It talks to Google's Gemini API for replies.

Each visitor gets their own conversations, and the recent messages are sent back to the model with every new question. That's what lets follow-ups work: ask "What is RAG?", then "What are its main components?", and the second question is understood as part of the same thread. You don't need an account to use it. Guests get threads tied to their session, and if you sign up later, those threads come with you so your history is there on any browser.

## What it does

- Multi-turn chat, with a system prompt and history length you can configure
- A sidebar of threads you can create, switch between and delete. Each one is named after its first message
- A model picker. Only the models listed in config can be chosen, and your pick is remembered for the session
- Falls back through a list of Gemini models. If one runs out of free-tier quota it's set aside for a while and the next one answers instead, so the bot keeps working
- Retries when a request fails for a temporary reason, and shows a normal error message instead of a stack trace if Gemini is down
- Optional sign-up and login, with rate limits on the chat, UI and auth routes
- Replies render Markdown and have a copy button

## Before you start

You'll need:

- PHP 8.3 or newer (this was built on 8.5) with the `sqlite3` and `pdo_sqlite` extensions
- Composer 2
- Node.js 20 or newer, and npm
- A Gemini API key. They're free from [Google AI Studio](https://aistudio.google.com/apikey)

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

Now open `.env` and paste in your key:

```env
GEMINI_API_KEY=your-key-here
```

That's the only value you have to fill in. Everything else already has a sensible default, but without a key the app can't answer anything.

## Running it

```bash
composer run dev
```

That one command runs the PHP server, the queue worker, log tailing and Vite together. Then open http://localhost:8000.

If you'd rather not run the asset watcher, run `npm run build` once and use `php artisan serve` instead.

## Settings

The chat settings live in `config/chatbot.php` and all of them read from environment variables:

| Variable | Default | What it does |
| --- | --- | --- |
| `GEMINI_API_KEY` | — | **Required.** Your Gemini key. It's only ever read from the environment, never committed |
| `GEMINI_MODELS` | `gemini-3.6-flash,gemini-3.5-flash-lite,gemini-3.1-flash-lite` | The fallback order, best model first. This list is also what the model picker shows |
| `GEMINI_MODEL_COOLDOWN_MINUTES` | `30` | How long to skip a model after it runs out of quota |
| `GEMINI_THINKING_LEVEL` | `low` | How much Gemini 3.x thinks before replying. Higher is slower |
| `GEMINI_TIMEOUT` | `60` | Request timeout, in seconds |
| `GEMINI_MAX_ATTEMPTS` | `3` | How many times to retry a model before moving to the next one |
| `CHAT_SYSTEM_PROMPT` | friendly, concise assistant | Added to the start of every conversation |
| `CHAT_HISTORY_LIMIT` | `20` | How many past messages get sent along as context |
| `CHAT_MAX_MESSAGE_LENGTH` | `4000` | Longest message a user can send, in characters |

Want to use something other than Gemini? Write a class that implements `App\Chat\Contracts\ChatProvider`, bind it in `AppServiceProvider`, and point `CHAT_PROVIDER` at it. Nothing else in the app calls Gemini directly, so that's the only place you have to touch.

## Tests

```bash
composer test                       # everything
php artisan test --compact          # same thing, shorter output
php artisan test --filter=ChatTest  # just one file
```

The feature tests cover the chat routes, auth, and handing a guest's threads over at sign-up. The unit tests cover the Gemini provider, the model fallback pool, prompt suggestions and how a thread is built for display. None of them make real network calls.

## Formatting

```bash
vendor/bin/pint --dirty
```

## Where things live

```
app/Chat/                  Chat logic: conversation store, model preference, small helper types
app/Chat/Contracts/        The ChatProvider interface, if you want to swap out Gemini
app/Chat/Providers/        Gemini client, its config object, and the model fallback pool
app/Http/Controllers/      Chat and auth controllers
app/Http/Requests/         Validation for incoming requests
config/chatbot.php         Provider, prompt, history and starter-prompt settings
resources/views/chat/      The Blade chat UI
resources/js/chat/         Front-end pieces (api, markdown, sidebar, menus)
tests/                     PHPUnit feature and unit tests
```
