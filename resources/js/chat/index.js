/**
 * Wires the chat window: sending messages, rendering replies, copying code,
 * and swapping between the opening screen and the live thread.
 */

import { sendMessage } from './api.js';
import { createMessage, createTypingIndicator } from './message.js';
import { initSidebar, syncConversation } from './sidebar.js';
import { initCopyButtons } from './copy.js';
import { initMenus } from './menu.js';

export function initChat(root = document) {
    initSidebar(root);
    initMenus(root);

    const log = root.querySelector('[data-chat-log]');
    const thread = root.querySelector('[data-chat-thread]');
    const empty = root.querySelector('[data-chat-empty]');
    const errorBar = root.querySelector('[data-chat-error]');
    const slots = {
        empty: root.querySelector('[data-composer-slot="empty"]'),
        thread: root.querySelector('[data-composer-slot="thread"]'),
    };

    if (!log || !thread) {
        return;
    }

    initCopyButtons(thread);

    const scrollToBottom = () => {
        log.scrollTop = log.scrollHeight;
    };

    const showError = (message) => {
        if (!errorBar) return;
        errorBar.querySelector('p').textContent = message;
        errorBar.classList.remove('hidden');
    };

    const hideError = () => errorBar?.classList.add('hidden');

    /**
     * Docks the composer at the foot of the thread the first time it is used.
     */
    const openThread = () => {
        const form = root.querySelector('[data-chat-form]');

        if (!form || slots.thread?.contains(form)) {
            return;
        }

        empty?.classList.add('hidden');
        log.classList.remove('hidden');
        slots.thread?.classList.remove('hidden');
        slots.thread?.append(form);
    };

    const append = (message) => {
        openThread();
        thread.append(createMessage(message));
        scrollToBottom();
    };

    async function submit(text) {
        const form = root.querySelector('[data-chat-form]');
        const input = form?.querySelector('textarea[name="message"]');
        const sendButton = form?.querySelector('[data-chat-send]');
        const message = (text ?? input?.value ?? '').trim();

        if (!form || !input || message === '' || sendButton.disabled) {
            return;
        }

        hideError();
        append({ role: 'user', content: message });

        input.value = '';
        autoGrow(input);

        setBusy({ input, sendButton, thread, log }, true);

        try {
            const { reply, conversation } = await sendMessage(form.action, message);
            append({ role: 'assistant', content: reply });
            syncConversation(conversation, root);
        } catch (error) {
            showError(error.message);
            syncConversation(error.conversation, root);
        } finally {
            setBusy({ input, sendButton, thread, log }, false);
        }
    }

    // The composer moves between slots, so events are delegated from the root.
    root.addEventListener('submit', (event) => {
        if (event.target.matches('[data-chat-form]')) {
            event.preventDefault();
            submit();
        }
    });

    root.addEventListener('input', (event) => {
        if (event.target.matches('[data-chat-form] textarea')) {
            autoGrow(event.target);
        }
    });

    root.addEventListener('keydown', (event) => {
        if (event.target.matches('[data-chat-form] textarea') && event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            submit();
        }
    });

    root.querySelectorAll('[data-chat-suggestion]').forEach((button) => {
        button.addEventListener('click', () => submit(button.dataset.chatSuggestion));
    });

    restoreHistory(root, thread);
    scrollToBottom();
    root.querySelector('[data-chat-form] textarea')?.focus();
}

function setBusy({ input, sendButton, thread, log }, busy) {
    sendButton.disabled = busy;
    input.disabled = busy;

    if (busy) {
        thread.append(createTypingIndicator());
        log.scrollTop = log.scrollHeight;

        return;
    }

    thread.querySelector('[data-chat-typing]')?.remove();
    input.focus();
}

function autoGrow(input) {
    input.style.height = 'auto';
    input.style.height = `${input.scrollHeight}px`;
}

/**
 * Replays messages persisted on the server into the thread.
 */
function restoreHistory(root, thread) {
    const payload = root.querySelector('[data-chat-history]')?.textContent;

    if (!payload) {
        return;
    }

    try {
        JSON.parse(payload).forEach((message) => thread.append(createMessage(message)));
    } catch {
        // A malformed payload should never blank the page; start with an empty thread.
    }
}
