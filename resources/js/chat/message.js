/**
 * Builds the nodes shown inside the conversation thread.
 *
 * User turns keep a bubble; assistant turns read as plain prose on the page,
 * the way a document does, so long replies stay comfortable to read.
 */

import { renderMarkdown } from './markdown.js';

const USER_BUBBLE =
    'max-w-[85%] rounded-2xl rounded-br-md border border-edge bg-panel px-4 py-2.5 text-[15.5px] leading-7 whitespace-pre-wrap break-words';

const ACTION_BUTTON =
    'rounded-lg p-1.5 text-faint transition hover:bg-panel hover:text-accent';

export function createMessage({ role, content }) {
    const node = role === 'user' ? userMessage(content) : assistantMessage(content);

    node.classList.add('chat-enter');

    return node;
}

function userMessage(content) {
    const row = document.createElement('div');
    row.className = 'flex justify-end pt-4 pb-1';
    row.dataset.role = 'user';

    const bubble = document.createElement('div');
    bubble.className = USER_BUBBLE;
    bubble.textContent = content;

    row.append(bubble);

    return row;
}

function assistantMessage(content) {
    const row = document.createElement('div');
    row.className = 'pt-1 pb-2';
    row.dataset.role = 'assistant';
    row.innerHTML = `
        <div class="md" data-message-body>${renderMarkdown(content)}</div>
        <div class="-ml-1.5 flex h-7 items-center gap-0.5">
            <button type="button" data-copy-message aria-label="Copy reply" class="${ACTION_BUTTON}">
                ${copyIcon()}
            </button>
        </div>`;

    return row;
}

export function createTypingIndicator() {
    const row = document.createElement('div');
    row.dataset.chatTyping = '';
    row.className = 'pt-2 pb-2';
    row.innerHTML = `
        <div class="flex items-center gap-1.5 text-accent" role="status" aria-label="Assistant is typing">
            <span class="chat-dot"></span><span class="chat-dot"></span><span class="chat-dot"></span>
        </div>`;

    return row;
}

function copyIcon() {
    return `<svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>
    </svg>`;
}
