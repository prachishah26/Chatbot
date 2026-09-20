/**
 * Copy-to-clipboard for code blocks and whole replies.
 *
 * Delegated from the thread so freshly rendered messages work without rebinding.
 */

const RESET_AFTER_MS = 1600;

export function initCopyButtons(thread) {
    thread.addEventListener('click', async (event) => {
        const codeButton = event.target.closest('[data-copy-code]');
        const messageButton = event.target.closest('[data-copy-message]');
        const button = codeButton ?? messageButton;

        if (!button) {
            return;
        }

        const source = codeButton
            ? codeButton.closest('[data-code-block]')?.querySelector('code')
            : messageButton.closest('[data-role="assistant"]')?.querySelector('[data-message-body]');

        if (!source) {
            return;
        }

        const copied = await copyText(source.innerText);

        // The icon button swaps to a tick so its width stays put; the code
        // block's text button reads better with a word.
        flash(button, copied, Boolean(messageButton));
    });
}

async function copyText(text) {
    try {
        await navigator.clipboard.writeText(text);

        return true;
    } catch {
        // Clipboard access is denied outside a secure context; fall back to a selection.
        return selectFallback(text);
    }
}

function selectFallback(text) {
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    document.body.append(area);
    area.select();

    const copied = document.execCommand?.('copy') ?? false;
    area.remove();

    return copied;
}

/**
 * Shows a short confirmation without losing the button's original label.
 */
function flash(button, copied, asIcon) {
    if (button.dataset.busy === '1') {
        return;
    }

    const original = button.innerHTML;
    button.dataset.busy = '1';

    if (copied && asIcon) {
        button.innerHTML = tickIcon();
    } else {
        button.textContent = copied ? 'Copied' : 'Press Ctrl+C';
    }

    setTimeout(() => {
        button.innerHTML = original;
        delete button.dataset.busy;
    }, RESET_AFTER_MS);
}

function tickIcon() {
    return `<svg class="size-4 text-accent" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"
        stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="m20 6-11 11-5-5"/>
    </svg>`;
}
