<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Active Chat Provider
    |--------------------------------------------------------------------------
    |
    | The key of the provider used to generate assistant replies. Providers are
    | resolved through App\Chat\Contracts\ChatProvider, so swapping this value
    | (and registering a matching binding) changes the backend with no other
    | application changes.
    |
    */

    'provider' => env('CHAT_PROVIDER', 'gemini'),

    /*
    |--------------------------------------------------------------------------
    | Conversation Behaviour
    |--------------------------------------------------------------------------
    */

    'system_prompt' => env('CHAT_SYSTEM_PROMPT', 'You are a friendly, concise assistant. Answer clearly and keep responses short unless the user asks for detail.'),

    // How many past messages (user + assistant) are replayed as context.
    'history_limit' => (int) env('CHAT_HISTORY_LIMIT', 20),

    // Maximum characters accepted from the user in a single message.
    'max_message_length' => (int) env('CHAT_MAX_MESSAGE_LENGTH', 4000),

    /*
    |--------------------------------------------------------------------------
    | Starter Prompts
    |--------------------------------------------------------------------------
    |
    | Shown as chips under the greeting on an empty thread. A random subset is
    | drawn on every page load, so add freely: the more entries here, the more
    | varied the empty state feels.
    |
    */

    // How many chips are shown at once.
    'suggestion_count' => (int) env('CHAT_SUGGESTION_COUNT', 3),

    'suggestions' => [
        'Explain RAG in simple terms',
        'Ideas for a weekend trip',
        'Write a haiku about the sea',
        'Summarise this week in tech',
        'Help me name a side project',
        'Plan a 30-minute workout',
        'Explain recursion with an analogy',
        'Draft a polite follow-up email',
        'Suggest a book worth re-reading',
        'Debug my regex for email addresses',
        'Turn my notes into a checklist',
        'What should I cook with what is in the fridge?',
        'Explain the difference between TCP and UDP',
        'Give me a five-minute meditation script',
        'Brainstorm blog post titles about testing',
        'Teach me a useful keyboard shortcut',
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Credentials are read from the environment only. Never commit a key.
    |
    */

    'providers' => [

        'gemini' => [
            'key' => env('GEMINI_API_KEY'),
            /*
             | Free-tier quota is counted per model per day, so a fallback chain
             | keeps the bot answering after the preferred model runs out.
             | Best quality first; comma-separated in the environment.
             */
            'models' => env('GEMINI_MODELS', 'gemini-3.6-flash,gemini-3.5-flash-lite,gemini-3.1-flash-lite'),

            // Minutes an exhausted model is skipped before being tried again.
            'model_cooldown_minutes' => (int) env('GEMINI_MODEL_COOLDOWN_MINUTES', 30),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'timeout' => (int) env('GEMINI_TIMEOUT', 60),
            'temperature' => (float) env('GEMINI_TEMPERATURE', 0.7),
            'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 1024),

            // Gemini 3.x reasons before replying; "low" keeps chat latency down.
            'thinking_level' => env('GEMINI_THINKING_LEVEL', 'low'),

            // The free tier sheds load under contention, so retry transient failures.
            'max_attempts' => (int) env('GEMINI_MAX_ATTEMPTS', 3),
            'retry_delay_ms' => (int) env('GEMINI_RETRY_DELAY_MS', 500),
        ],

    ],

];
