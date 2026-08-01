/**
 * Shared HTTP helper for authenticated groupware mutations.
 *
 * - Reads Laravel's current XSRF token cookie on every request.
 * - Sets X-XSRF-TOKEN, X-Requested-With and Accept: application/json.
 * - Preserves caller headers; never sets Content-Type for FormData.
 * - Converts non-2xx JSON responses into a typed HttpError.
 */

export class HttpError extends Error {
    constructor(
        public readonly status: number,
        public readonly payload: unknown,
    ) {
        super(`HTTP ${status}`);
        this.name = 'HttpError';
    }

    /** Extract a human-readable message from Laravel-style error payloads. */
    messageText(): string {
        const payload = this.payload as {
            message?: string;
            errors?: Record<string, string[]>;
        } | null;

        if (payload?.errors) {
            const first = Object.values(payload.errors).flat()[0];

            if (first) {
                return first;
            }
        }

        return payload?.message ?? `HTTP ${this.status}`;
    }
}

export function xsrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((value) => value.startsWith('XSRF-TOKEN='));

    if (!cookie) {
        throw new Error('XSRF token cookie is missing.');
    }

    return decodeURIComponent(cookie.slice('XSRF-TOKEN='.length));
}

export async function apiFetch(
    input: RequestInfo | URL,
    init: RequestInit = {},
): Promise<Response> {
    const token = xsrfToken();

    const headers = new Headers(init.headers);
    headers.set('X-XSRF-TOKEN', token);
    headers.set('X-Requested-With', 'XMLHttpRequest');
    headers.set('Accept', 'application/json');

    const response = await fetch(input, {
        ...init,
        headers,
        credentials: 'same-origin',
    });

    if (!response.ok) {
        const payload = (await response.json().catch(() => null)) as unknown;

        throw new HttpError(response.status, payload);
    }

    return response;
}

/** POST JSON and decode the response body (or null for empty responses). */
export async function apiJson<T>(
    input: RequestInfo | URL,
    init: RequestInit = {},
): Promise<T> {
    const headers = new Headers(init.headers);

    if (!(init.body instanceof FormData)) {
        headers.set('Content-Type', 'application/json');
    }

    const response = await apiFetch(input, { ...init, headers });

    if (response.status === 204) {
        return undefined as T;
    }

    return (await response.json()) as T;
}
