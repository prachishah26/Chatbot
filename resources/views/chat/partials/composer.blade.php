{{-- The message box. Shared by the empty state and the live thread. --}}
<form data-chat-form action="{{ route('chat.messages.store') }}" method="POST" novalidate
    class="mx-auto w-full max-w-3xl px-4">

    <div class="flex items-end gap-2 rounded-2xl border border-edge bg-panel p-2 shadow-[0_6px_24px_-14px_rgba(0,0,0,0.45)] transition focus-within:border-accent/50 focus-within:shadow-[0_0_0_1px_rgba(13,148,136,0.25),0_6px_24px_-14px_rgba(0,0,0,0.45)]">
        <label for="chat-input" class="sr-only">Message</label>
        <textarea id="chat-input" name="message" rows="1" maxlength="{{ $maxLength }}" autocomplete="off"
            placeholder="Ask me anything"
            class="max-h-52 flex-1 resize-none bg-transparent px-3 py-2.5 text-[16px] leading-6 text-ink placeholder:text-faint focus:outline-none disabled:opacity-60"></textarea>

        <button type="submit" data-chat-send aria-label="Send message"
            class="grid size-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-accent to-accent-deep text-white transition hover:brightness-110 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent disabled:cursor-not-allowed disabled:from-panel-hi disabled:to-panel-hi disabled:text-faint">
            <x-icon-send />
        </button>
    </div>

    <p class="px-1 py-2 text-center text-[11px] text-faint">
        Enter to send &middot; Shift + Enter for a new line
    </p>
</form>
