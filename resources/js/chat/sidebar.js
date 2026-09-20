/**
 * Thread rail behaviour: the off-canvas toggle on small screens, and keeping
 * the list in step with a thread that was just named by its first message.
 */

const TODAY = 'Today';

export function initSidebar(root = document) {
    const sidebar = root.querySelector('[data-sidebar]');
    const backdrop = root.querySelector('[data-sidebar-backdrop]');

    if (!sidebar) {
        return;
    }

    const open = () => {
        sidebar.classList.remove('-translate-x-full');
        backdrop?.classList.remove('hidden');
    };

    const close = () => {
        sidebar.classList.add('-translate-x-full');
        backdrop?.classList.add('hidden');
    };

    root.querySelector('[data-sidebar-open]')?.addEventListener('click', open);
    root.querySelector('[data-sidebar-close]')?.addEventListener('click', close);
    backdrop?.addEventListener('click', close);

    root.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });
}

/**
 * Inserts or renames the active thread once the server has titled it, so the
 * rail matches ChatGPT's behaviour without a page reload.
 */
export function syncConversation(thread, root = document) {
    const list = root.querySelector('[data-conversation-list]');

    if (!list || !thread) {
        return;
    }

    nameHeader(thread, root);

    const existing = list.querySelector(`[data-conversation="${CSS.escape(String(thread.id))}"]`);

    if (existing) {
        const label = existing.querySelector('[data-conversation-title]');

        if (label) {
            label.textContent = thread.title;
        }

        return;
    }

    const today = list.querySelector(`[data-conversation-group="${TODAY}"]`);

    if (!today) {
        return;
    }

    root.querySelector('[data-conversation-empty]')?.classList.add('hidden');
    today.classList.remove('hidden');
    today.querySelector('[data-conversation-group-items]')?.prepend(buildEntry(thread));
}

/**
 * The thread's name shows in the tab, and in the header only if that optional
 * element is present.
 */
function nameHeader(thread, root) {
    document.title = thread.title;

    const header = root.querySelector('[data-thread-title]');

    if (header) {
        header.textContent = thread.title;
    }
}

function buildEntry(thread) {
    const row = document.createElement('div');
    row.className = 'group relative';
    row.dataset.conversation = String(thread.id);

    const link = document.createElement('a');
    link.href = thread.url;
    link.className =
        'block truncate rounded-lg py-2 pr-9 pl-2.5 text-sm bg-panel font-medium text-ink ' +
        'shadow-[inset_2px_0_0_0_var(--color-accent)]';
    link.dataset.conversationTitle = '';
    link.textContent = thread.title;

    row.append(link);

    return row;
}
