import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ServerResource } from '@/types';
import MemberDialog from './MemberDialog.svelte';

afterEach(cleanup);

describe('MemberDialog project settings', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=token-123; Path=/; Max-Age=3600';
        vi.restoreAllMocks();
    });

    it('updates the project name, dates, and content', async () => {
        const server: ServerResource = {
            id: 1,
            name: 'Before',
            description: null,
            starts_on: null,
            ends_on: null,
            created_by: 1,
        };
        const updated = {
            ...server,
            name: 'Project Alpha',
            description: 'Project content',
            starts_on: '2026-08-01',
            ends_on: '2026-08-31',
        };
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ server: updated }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        const onUpdated = vi.fn();
        render(MemberDialog, {
            props: {
                server,
                members: [],
                onUpdated,
                onClose: vi.fn(),
            },
        });

        await fireEvent.input(screen.getByLabelText('プロジェクト名'), {
            target: { value: 'Project Alpha' },
        });
        await fireEvent.input(screen.getByLabelText('開始日'), {
            target: { value: '2026-08-01' },
        });
        await fireEvent.input(screen.getByLabelText('終了日'), {
            target: { value: '2026-08-31' },
        });
        await fireEvent.input(screen.getByLabelText('内容'), {
            target: { value: 'Project content' },
        });
        await fireEvent.click(screen.getByRole('button', { name: '保存' }));

        await waitFor(() => expect(onUpdated).toHaveBeenCalledWith(updated));
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/servers/1');
        expect(init.method).toBe('PATCH');
        expect(JSON.parse(init.body)).toEqual({
            name: 'Project Alpha',
            description: 'Project content',
            starts_on: '2026-08-01',
            ends_on: '2026-08-31',
        });
        expect(screen.getByText('プロジェクト情報を保存しました')).toBeTruthy();
    });
});
