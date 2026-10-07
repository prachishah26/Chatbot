---
paths:
  - 'app/Http/**'
  - 'routes/**'
---

# HTTP Layer

## Thin controllers

Controllers handle transport only: take a FormRequest, call `ConversationService`, `ChatProvider` or `ModelSelectionService`, and return a view, redirect or `ApiResponse`. Put rules and queries in `app/Chat/`.

## JSON goes through ApiResponse

Build every JSON body with `App\Http\Responses\ApiResponse::success()` / `failure()`. The shape is `{success, data, error}`, and `resources/js/chat/api.js` depends on it. The `error` string is shown to the visitor verbatim, so it must be a fixed, user-safe sentence.

## Every route is rate limited

Each non-GET route sits under a named limiter from `AppServiceProvider`: `throttle:chat` for anything that calls an LLM, `throttle:chat-ui` for cheap actions (thread actions, model choice, logout), and `throttle:auth` for login and register submissions. A new route must pick one.

## Validation lives in FormRequests

Validate all input in `app/Http/Requests/*` and read it through typed accessors (e.g. `StoreMessageRequest::message()`), not `$request->input()` in the controller. Message length comes from `config('chatbot.max_message_length')`.

## Routes

Use named routes and `route()`. Constrain conversation parameters with `->whereUlid('conversation')`. Web routes keep CSRF on; the front end sends `X-CSRF-TOKEN`.

## Auth

After login or registration: regenerate the session, then call `GuestConversationService::claim()`. Login failures use one generic message, so the form cannot reveal which emails exist.
