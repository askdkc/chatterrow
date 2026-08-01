import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ServerDialog from './ServerDialog.svelte';

afterEach(cleanup);

describe('ServerDialog', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=token-123; Path=/; Max-Age=3600';
        vi.restoreAllMocks();
        vi.stubGlobal('location', { href: '' });
    });

    it('does not create a project when Enter is pressed', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
        render(ServerDialog, { props: { onClose: vi.fn() } });

        const nameInput = screen.getByPlaceholderText('例: プロジェクトA');
        await fireEvent.input(nameInput, { target: { value: 'new-project' } });
        await fireEvent.keyDown(nameInput, { key: 'Enter' });

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it.each([
        ['Cmd+Enter', { metaKey: true }],
        ['Ctrl+Enter', { ctrlKey: true }],
    ])('creates a project with %s', async (_, modifier) => {
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ server: { id: 42 } }), {
                status: 201,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        render(ServerDialog, { props: { onClose: vi.fn() } });

        const nameInput = screen.getByPlaceholderText('例: プロジェクトA');
        await fireEvent.input(nameInput, { target: { value: 'new-project' } });
        await fireEvent.keyDown(nameInput, { key: 'Enter', ...modifier });

        await waitFor(() => expect(fetchMock).toHaveBeenCalledOnce());
    });
});
