<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Chat\Conversations\GuestConversationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuthenticatedSessionController extends Controller
{
    /**
     * Deliberately the same for a wrong password and an unknown address, so the
     * form cannot be used to discover which emails have accounts.
     */
    private const FAILED = 'Those credentials do not match our records.';

    public function __construct(private readonly GuestConversationService $guestConversations) {}

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->credentials(), $request->remember())) {
            throw ValidationException::withMessages(['email' => self::FAILED]);
        }

        $user = $request->user();

        // A fresh session id closes off session fixation.
        $request->session()->regenerate();

        if ($user instanceof User) {
            $this->guestConversations->claim($request->session(), $user);
        }

        return redirect()->intended(route('chat.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('chat.index');
    }
}
