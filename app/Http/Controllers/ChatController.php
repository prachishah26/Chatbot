<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Chat\Conversations\ConversationService;
use App\Chat\Llm\ModelSelectionService;
use App\Chat\Presentation\ConversationTimeline;
use App\Chat\Presentation\PromptSuggestions;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the chat window. Actions taken inside it live in their own
 * controllers: MessageController, ConversationController and ModelSelectionController.
 */
final class ChatController extends Controller
{
    public function __construct(
        private readonly ConversationService $conversations,
        private readonly ModelSelectionService $modelSelection,
    ) {}

    /**
     * Render the chat window on the visitor's current thread.
     */
    public function index(Request $request): View
    {
        return $this->window($request, $this->conversations->current($request->session()));
    }

    /**
     * Open one of the visitor's own threads.
     */
    public function show(Request $request, string $conversation): View
    {
        $thread = $this->conversations->find($request->session(), $conversation);

        abort_if($thread === null, Response::HTTP_NOT_FOUND);

        $this->conversations->makeCurrent($request->session(), $thread);

        return $this->window($request, $thread);
    }

    private function window(Request $request, Conversation $conversation): View
    {
        return view('chat.index', [
            'conversation' => $conversation,
            'conversationGroups' => ConversationTimeline::group($this->conversations->sidebar($request->session()), now()),
            'messages' => $this->conversations->messages($conversation)
                ->map(static fn (Message $message): array => $message->toChatArray())
                ->all(),
            'maxLength' => (int) config('chatbot.max_message_length', 4000),
            'suggestions' => PromptSuggestions::pick(
                (array) config('chatbot.suggestions', []),
                (int) config('chatbot.suggestion_count', 3),
            ),
            'modelGroups' => $this->modelSelection->groupedOptions(),
            'currentChoice' => $this->modelSelection->currentChoice($request->session()),
        ]);
    }
}
