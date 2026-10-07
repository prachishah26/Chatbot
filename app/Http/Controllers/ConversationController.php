<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Chat\Conversations\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ConversationController extends Controller
{
    public function __construct(private readonly ConversationService $conversations) {}

    /**
     * Begin a fresh thread with no history from the current one.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->conversations->startNew($request->session());

        return redirect()->route('chat.index');
    }

    /**
     * Remove one of the visitor's threads entirely.
     */
    public function destroy(Request $request, string $conversation): RedirectResponse
    {
        $thread = $this->conversations->find($request->session(), $conversation);

        abort_if($thread === null, Response::HTTP_NOT_FOUND);

        $this->conversations->delete($thread);
        $this->conversations->startNew($request->session());

        return redirect()->route('chat.index');
    }
}
