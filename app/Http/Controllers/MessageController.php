<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Chat\Contracts\ChatProvider;
use App\Chat\Conversations\ConversationService;
use App\Chat\Data\Role;
use App\Chat\Exceptions\ChatProviderException;
use App\Chat\Llm\ModelSelectionService;
use App\Http\Requests\StoreMessageRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class MessageController extends Controller
{
    /**
     * Shown to the visitor whenever the provider fails. Upstream detail is logged, never returned.
     */
    public const PROVIDER_ERROR = 'The assistant is unavailable right now. Please try again in a moment.';

    public function __construct(
        private readonly ConversationService $conversations,
        private readonly ChatProvider $provider,
        private readonly ModelSelectionService $modelSelection,
    ) {}

    /**
     * Accept a user message and return the assistant's reply.
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        $conversation = $this->conversations->current($request->session());

        // History is captured before persisting so the new message is not duplicated.
        $history = $this->conversations->recentTurns($conversation);
        $userMessage = $this->conversations->addMessage($conversation, Role::User, $request->message());

        try {
            $reply = $this->provider->reply(
                $request->message(),
                $history,
                $this->modelSelection->current($request->session()),
            );
        } catch (ChatProviderException $e) {
            Log::error('Chat provider failed.', [
                'conversation_id' => $conversation->id,
                'exception' => $e->getMessage(),
            ]);

            return ApiResponse::failure(
                self::PROVIDER_ERROR,
                Response::HTTP_SERVICE_UNAVAILABLE,
                $this->exchange($conversation, $userMessage, null),
            );
        }

        $assistantMessage = $this->conversations->addMessage($conversation, Role::Assistant, $reply);

        return ApiResponse::success($this->exchange($conversation, $userMessage, $assistantMessage));
    }

    /**
     * One round trip as the front end renders it. The user's message is always
     * present, so it stays on screen even when the assistant failed to answer.
     *
     * @return array{conversation: array{id: string, title: string, url: string}, user: array<string, mixed>, assistant: array<string, mixed>|null}
     */
    private function exchange(Conversation $conversation, Message $userMessage, ?Message $assistantMessage): array
    {
        $conversation->refresh();

        return [
            'conversation' => [
                'id' => $conversation->public_id,
                'title' => $conversation->displayTitle(),
                'url' => route('chat.show', $conversation),
            ],
            'user' => $userMessage->toChatArray(),
            'assistant' => $assistantMessage?->toChatArray(),
        ];
    }
}
