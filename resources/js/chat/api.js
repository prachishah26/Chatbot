/**
 * Thin wrapper around the chat endpoints.
 *
 * Every failure is surfaced as an Error carrying a user-safe message.
 */

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const headers = () => ({
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-CSRF-TOKEN': csrfToken(),
    'X-Requested-With': 'XMLHttpRequest',
});

const GENERIC_ERROR = 'Something went wrong. Please try again.';

async function request(url, options) {
    let response;

    try {
        response = await fetch(url, { ...options, headers: headers() });
    } catch {
        throw new Error('You appear to be offline. Check your connection and try again.');
    }

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
        const error = new Error(
            payload?.error ?? payload?.errors?.message?.[0] ?? payload?.message ?? GENERIC_ERROR,
        );

        // The thread summary still arrives on failure, so the rail stays correct.
        error.conversation = payload?.data?.conversation ?? null;

        throw error;
    }

    return payload;
}

/**
 * @returns {Promise<{reply: string, conversation: ?object}>}
 */
export async function sendMessage(url, message) {
    const payload = await request(url, {
        method: 'POST',
        body: JSON.stringify({ message }),
    });

    return {
        reply: payload?.data?.assistant?.content ?? '',
        conversation: payload?.data?.conversation ?? null,
    };
}
