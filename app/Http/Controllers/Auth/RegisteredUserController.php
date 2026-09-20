<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Chat\ConversationStore;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class RegisteredUserController extends Controller
{
    public function __construct(private readonly ConversationStore $store) {}

    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        // The model casts `password` to hashed, so the plain value never lands.
        $user = User::create($request->credentials());

        Auth::login($user);

        $request->session()->regenerate();
        $this->store->claimGuestThreads($request->session(), $user);

        return redirect()->route('chat.index');
    }
}
