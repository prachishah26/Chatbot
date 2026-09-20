<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Chat\Contracts\ChatProvider;
use App\Chat\ConversationStore;
use App\Chat\Exceptions\ChatProviderException;
use App\Chat\ModelPreference;
use App\Chat\Support\PromptSuggestions;
use App\Chat\Support\Role;
use App\Chat\Support\ThreadTimeline;
use App\Http\Requests\ChooseModelRequest;
use App\Http\Requests\SendMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

final class ChatController extends Controller
{
    private const PROVIDER_ERROR = 'The assistant is unavailable right now. Please try again in a moment.';

    public function __construct(
        private readonly ConversationStore $store,
        private readonly ChatProvider $provider,
        private readonly ModelPreference $models,
    ) {}

    /**
     * Render the chat window on the visitor's current thread.
     */
    public function index(Request $request): View
    {
        return $this->window($request, $this->store->current($request->session()));
    }

    /**
     * Open one of the visitor's own threads.
     */
    public function show(Request $request, string $conversation): View
    {
        $thread = $this->store->find($request->session(), $conversation);

        abort_if($thread === null, Response::HTTP_NOT_FOUND);

        $this->store->makeCurrent($request->session(), $thread);

        return $this->window($request, $thread);
    }

    /**
     * Accept a user message and return the assistant's reply.
     */
    public function store(SendMessageRequest $request): JsonResponse
    {
        $conversation = $this->store->current($request->session());

        // History is captured before persisting so the new message is not duplicated.
        $history = $this->store->recentTurns($conversation);
        $userMessage = $this->store->append($conversation, Role::User, $request->message());

        try {
            $reply = $this->provider->reply(
                $request->message(),
                $history,
                $this->models->current($request->session()),
            );
        } catch (ChatProviderException $e) {
            Log::error('Chat provider failed.', [
                'conversation_id' => $conversation->id,
                'exception' => $e->getMessage(),
            ]);

            return $this->fail(self::PROVIDER_ERROR, $conversation, $userMessage, Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $assistantMessage = $this->store->append($conversation, Role::Assistant, $reply);

        return response()->json([
            'success' => true,
            'data' => [
                'conversation' => $this->threadSummary($conversation->refresh()),
                'user' => $userMessage->toChatArray(),
                'assistant' => $assistantMessage->toChatArray(),
            ],
            'error' => null,
        ]);
    }

    /**
     * Switch the model used for subsequent replies.
     */
    public function chooseModel(ChooseModelRequest $request): RedirectResponse
    {
        $this->models->choose($request->session(), $request->model());

        return back();
    }

    /**
     * Begin a fresh thread with no history from the current one.
     */
    public function startNew(Request $request): RedirectResponse
    {
        $this->store->startNew($request->session());

        return redirect()->route('chat.index');
    }

    /**
     * Remove one of the visitor's threads entirely.
     */
    public function destroy(Request $request, string $conversation): RedirectResponse
    {
        $thread = $this->store->find($request->session(), $conversation);

        abort_if($thread === null, Response::HTTP_NOT_FOUND);

        $this->store->delete($thread);
        $this->store->startNew($request->session());

        return redirect()->route('chat.index');
    }

    private function window(Request $request, Conversation $conversation): View
    {
        return view('chat.index', [
            'conversation' => $conversation,
            'conversationGroups' => ThreadTimeline::group($this->store->sidebar($request->session()), now()),
            'messages' => $this->store->messages($conversation)
                ->map(static fn (Message $message): array => $message->toChatArray())
                ->all(),
            'maxLength' => (int) config('chatbot.max_message_length', 4000),
            'suggestions' => PromptSuggestions::pick(
                (array) config('chatbot.suggestions', []),
                (int) config('chatbot.suggestion_count', 3),
            ),
            'modelOptions' => $this->models->options(),
            'currentModel' => $this->models->current($request->session()),
        ]);
    }

    /**
     * @return array{id: string, title: string, url: string}
     */
    private function threadSummary(Conversation $conversation): array
    {
        return [
            'id' => $conversation->public_id,
            'title' => $conversation->displayTitle(),
            'url' => route('chat.show', $conversation),
        ];
    }

    private function fail(string $error, Conversation $conversation, Message $userMessage, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'data' => [
                'conversation' => $this->threadSummary($conversation->refresh()),
                'user' => $userMessage->toChatArray(),
                'assistant' => null,
            ],
            'error' => $error,
        ], $status);
    }
}
