import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { apiFetch, apiJson, csrfToken, HttpError } from './http';

function setCsrfMeta(content: string | null): void {
    let meta = document.querySelector<HTMLMetaElement>(
        'meta[name="csrf-token"]',
    );

    if (!meta) {
        meta = document.createElement('meta');
        meta.name = 'csrf-token';
        document.head.appendChild(meta);
    }

    if (content === null) {
        meta.remove();
    } else {
        meta.content = content;
    }
}

describe('csrfToken', () => {
    beforeEach(() => {
        document.head.innerHTML = '';
    });

    it('returns the meta content when present', () => {
        setCsrfMeta('token-123');
        expect(csrfToken()).toBe('token-123');
    });

    it('throws when the meta tag is absent', () => {
        expect(() => csrfToken()).toThrow(/CSRF token is missing/);
    });

    it('throws when the meta content is empty', () => {
        setCsrfMeta('');
        expect(() => csrfToken()).toThrow(/CSRF token is missing/);
    });
});

describe('apiFetch', () => {
    beforeEach(() => {
        setCsrfMeta('token-123');
        vi.restoreAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('sets CSRF, XHR and Accept headers and same-origin credentials', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValue(new Response('{}', { status: 200 }));
        vi.stubGlobal('fetch', fetchMock);

        await apiFetch('/servers', { method: 'POST' });

        const [input, init] = fetchMock.mock.calls[0];
        expect(input).toBe('/servers');
        const headers = new Headers(init.headers);
        expect(headers.get('X-CSRF-TOKEN')).toBe('token-123');
        expect(headers.get('X-Requested-With')).toBe('XMLHttpRequest');
        expect(headers.get('Accept')).toBe('application/json');
        expect(init.credentials).toBe('same-origin');
        expect(init.method).toBe('POST');
    });

    it('preserves caller headers', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValue(new Response('{}', { status: 200 }));
        vi.stubGlobal('fetch', fetchMock);

        await apiFetch('/x', { headers: { 'X-Custom': 'yes' } });

        const headers = new Headers(fetchMock.mock.calls[0][1].headers);
        expect(headers.get('X-Custom')).toBe('yes');
        expect(headers.get('X-CSRF-TOKEN')).toBe('token-123');
    });

    it('throws HttpError with status and payload on non-2xx JSON', async () => {
        const body = JSON.stringify({
            message: 'Validation failed',
            errors: { name: ['required'] },
        });
        vi.stubGlobal(
            'fetch',
            vi.fn().mockResolvedValue(
                new Response(body, {
                    status: 422,
                    headers: { 'Content-Type': 'application/json' },
                }),
            ),
        );

        const error = await apiFetch('/servers', { method: 'POST' }).catch(
            (e: unknown) => e,
        );
        expect(error).toBeInstanceOf(HttpError);

        if (error instanceof HttpError) {
            expect(error.status).toBe(422);
            expect(error.messageText()).toBe('required');
        }
    });

    it('throws HttpError even when the error body is not JSON', async () => {
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockResolvedValue(
                    new Response('Server Error', { status: 500 }),
                ),
        );

        const error = await apiFetch('/x').catch((e: unknown) => e);
        expect(error).toBeInstanceOf(HttpError);

        if (error instanceof HttpError) {
            expect(error.status).toBe(500);
            expect(error.messageText()).toBe('HTTP 500');
        }
    });

    it('throws a clear error when the CSRF meta is absent', async () => {
        document.head.innerHTML = '';
        vi.stubGlobal('fetch', vi.fn());

        await expect(apiFetch('/x')).rejects.toThrow(/CSRF token is missing/);
        expect(vi.mocked(fetch)).not.toHaveBeenCalled();
    });
});

describe('apiJson', () => {
    beforeEach(() => {
        setCsrfMeta('token-123');
        vi.restoreAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('sets Content-Type application/json for JSON bodies', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValue(new Response('{"ok":true}', { status: 200 }));
        vi.stubGlobal('fetch', fetchMock);

        await apiJson('/servers', {
            method: 'POST',
            body: JSON.stringify({ name: 'x' }),
        });

        const headers = new Headers(fetchMock.mock.calls[0][1].headers);
        expect(headers.get('Content-Type')).toBe('application/json');
    });

    it('does not set Content-Type for FormData', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValue(new Response('{"ok":true}', { status: 200 }));
        vi.stubGlobal('fetch', fetchMock);

        const form = new FormData();
        form.append('file', new Blob(['x']), 'a.txt');
        await apiJson('/files', { method: 'POST', body: form });

        const headers = new Headers(fetchMock.mock.calls[0][1].headers);
        expect(headers.has('Content-Type')).toBe(false);
    });

    it('returns the decoded JSON payload', async () => {
        vi.stubGlobal(
            'fetch',
            vi
                .fn()
                .mockResolvedValue(new Response('{"id":7}', { status: 201 })),
        );

        const data = await apiJson<{ id: number }>('/channels', {
            method: 'POST',
        });
        expect(data.id).toBe(7);
    });
});
