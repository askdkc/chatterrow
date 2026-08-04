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
                invitations: [],
                canManage: true,
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
        await fireEvent.input(screen.getByLabelText('内容（任意）'), {
            target: { value: 'Project content' },
        });
        await fireEvent.click(screen.getByRole('button', { name: '保存' }));

        await waitFor(() => expect(onUpdated).toHaveBeenCalledWith(updated));
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/servers/1');
        expect(init.method).toBe('POST');
        const form = init.body as FormData;
        expect(form.get('_method')).toBe('PATCH');
        expect(form.get('name')).toBe('Project Alpha');
        expect(form.get('description')).toBe('Project content');
        expect(form.get('starts_on')).toBe('2026-08-01');
        expect(form.get('ends_on')).toBe('2026-08-31');
        expect(screen.getByText('プロジェクト情報を保存しました')).toBeTruthy();
    });

    it('previews and updates a dropped project icon', async () => {
        const server: ServerResource = {
            id: 1,
            name: 'Project Alpha',
            description: null,
            icon_url: null,
            starts_on: null,
            ends_on: null,
            created_by: 1,
        };
        const updated = { ...server, icon_url: '/servers/1/icon?v=new' };
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ server: updated }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        Object.defineProperty(URL, 'createObjectURL', {
            configurable: true,
            value: vi.fn(() => 'blob:project-icon'),
        });
        Object.defineProperty(URL, 'revokeObjectURL', {
            configurable: true,
            value: vi.fn(),
        });
        const onUpdated = vi.fn();

        render(MemberDialog, {
            props: {
                server,
                members: [],
                invitations: [],
                canManage: true,
                onUpdated,
                onClose: vi.fn(),
            },
        });

        const icon = new File(['icon'], 'project.png', {
            type: 'image/png',
        });
        const dropTarget = screen.getByRole('button', {
            name: 'プロジェクトアイコン画像を選択またはドロップ',
        });
        const dataTransfer = {
            files: [icon],
            types: ['Files'],
            dropEffect: 'none',
        };
        await fireEvent.dragEnter(dropTarget, { dataTransfer });

        expect(screen.getByText('ドロップ')).toBeTruthy();

        await fireEvent.drop(dropTarget, {
            dataTransfer,
        });

        expect(
            document.querySelector('img[src="blob:project-icon"]'),
        ).toBeTruthy();

        await fireEvent.click(screen.getByRole('button', { name: '保存' }));
        await waitFor(() => expect(onUpdated).toHaveBeenCalledWith(updated));

        const [, init] = fetchMock.mock.calls[0];
        const form = init.body as FormData;
        expect(init.method).toBe('POST');
        expect(form.get('_method')).toBe('PATCH');
        expect(form.get('icon')).toBe(icon);
        expect(
            document.querySelector('img[src="/servers/1/icon?v=new"]'),
        ).toBeTruthy();
    });

    it('creates a pending invitation instead of immediately adding a member', async () => {
        const server: ServerResource = {
            id: 1,
            name: 'Project Alpha',
            description: null,
            starts_on: null,
            ends_on: null,
            created_by: 1,
        };
        const owner = { id: 1, name: 'Owner', email: 'owner@example.com' };
        const added = {
            id: 2,
            name: 'New Member',
            email: 'member@example.com',
        };
        const invitation = {
            id: 9,
            email: added.email,
            status: 'pending' as const,
            registered: true,
            sent_at: '2026-08-04T10:00:00Z',
            responded_at: null,
            user: added,
        };
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ invitation, delivery: 'in_app' }), {
                status: 201,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        const onMembersUpdated = vi.fn();

        render(MemberDialog, {
            props: {
                server,
                members: [owner],
                invitations: [],
                canManage: true,
                onMembersUpdated,
                onClose: vi.fn(),
            },
        });

        await fireEvent.input(
            screen.getByLabelText('メンバーのメールアドレス'),
            { target: { value: added.email } },
        );
        await fireEvent.click(screen.getByRole('button', { name: '招待' }));

        await waitFor(() => expect(screen.getByText('回答待ち')).toBeTruthy());
        expect(screen.getByText('New Member')).toBeTruthy();
        expect(onMembersUpdated).not.toHaveBeenCalled();
        expect(fetchMock.mock.calls[0][0]).toBe('/servers/1/invitations');
    });

    it('shows rejected invitations and lets the owner resend or delete them', async () => {
        const server: ServerResource = {
            id: 1,
            name: 'Project Alpha',
            description: null,
            starts_on: null,
            ends_on: null,
            created_by: 1,
        };
        const declined = {
            id: 9,
            email: 'member@example.com',
            status: 'declined' as const,
            registered: true,
            sent_at: '2026-08-04T10:00:00Z',
            responded_at: '2026-08-04T10:05:00Z',
            user: {
                id: 2,
                name: 'New Member',
                email: 'member@example.com',
            },
        };
        const resent = {
            ...declined,
            status: 'pending' as const,
            responded_at: null,
        };
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ invitation: resent }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
            )
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ ok: true }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
            );
        vi.stubGlobal('fetch', fetchMock);

        render(MemberDialog, {
            props: {
                server,
                members: [],
                invitations: [declined],
                canManage: true,
                onClose: vi.fn(),
            },
        });

        expect(screen.getByText('拒否')).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: '再送' }));
        await waitFor(() => expect(screen.getByText('回答待ち')).toBeTruthy());
        await fireEvent.click(
            screen.getByRole('button', { name: '招待を削除' }),
        );

        await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
        expect(fetchMock.mock.calls[0][0]).toBe(
            '/servers/1/invitations/9/resend',
        );
        expect(fetchMock.mock.calls[1][0]).toBe('/servers/1/invitations/9');
    });

    it('promotes and demotes additional project administrators', async () => {
        const server: ServerResource = {
            id: 1,
            name: 'Project Alpha',
            description: null,
            starts_on: null,
            ends_on: null,
            created_by: 1,
        };
        const owner = {
            id: 1,
            name: 'Owner',
            email: 'owner@example.com',
            pivot: { role: 'admin' as const },
        };
        const member = {
            id: 2,
            name: 'New Member',
            email: 'member@example.com',
            pivot: { role: 'member' as const },
        };
        const administrator = {
            ...member,
            pivot: { role: 'admin' as const },
        };
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ user: administrator }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
            )
            .mockResolvedValueOnce(
                new Response(JSON.stringify({ user: member }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json' },
                }),
            );
        vi.stubGlobal('fetch', fetchMock);
        const onMembersUpdated = vi.fn();

        render(MemberDialog, {
            props: {
                server,
                members: [owner, member],
                invitations: [],
                canManage: true,
                onMembersUpdated,
                onClose: vi.fn(),
            },
        });

        expect(
            screen.queryByRole('button', { name: 'Ownerを管理者から外す' }),
        ).toBeNull();

        await fireEvent.click(
            screen.getByRole('button', { name: '管理者にする' }),
        );

        await waitFor(() =>
            expect(
                screen.getByRole('button', { name: '管理者から外す' }),
            ).toBeTruthy(),
        );
        expect(fetchMock.mock.calls[0][0]).toBe('/servers/1/members/2/role');
        expect(fetchMock.mock.calls[0][1].method).toBe('PATCH');
        expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
            role: 'admin',
        });
        expect(onMembersUpdated).toHaveBeenLastCalledWith([
            owner,
            administrator,
        ]);

        await fireEvent.click(
            screen.getByRole('button', { name: '管理者から外す' }),
        );

        await waitFor(() =>
            expect(
                screen.getByRole('button', { name: '管理者にする' }),
            ).toBeTruthy(),
        );
        expect(JSON.parse(fetchMock.mock.calls[1][1].body)).toEqual({
            role: 'member',
        });
        expect(onMembersUpdated).toHaveBeenLastCalledWith([owner, member]);
    });

    it('archives a project after confirmation', async () => {
        const server: ServerResource = {
            id: 1,
            name: 'Project Alpha',
            description: null,
            starts_on: null,
            ends_on: null,
            created_by: 1,
        };
        const archived = { ...server, archived_at: '2026-08-04T10:00:00Z' };
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ server: archived }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        const onArchived = vi.fn();

        render(MemberDialog, {
            props: {
                server,
                members: [],
                invitations: [],
                canManage: true,
                onArchived,
                onClose: vi.fn(),
            },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: 'アーカイブ' }),
        );
        const confirmationButtons = screen.getAllByRole('button', {
            name: 'アーカイブ',
        });
        await fireEvent.click(confirmationButtons.at(-1)!);

        await waitFor(() => expect(onArchived).toHaveBeenCalledWith(archived));
        expect(fetchMock.mock.calls[0][0]).toBe('/servers/1/archive');
        expect(fetchMock.mock.calls[0][1].method).toBe('PATCH');
    });

    it('permanently deletes a project after confirmation', async () => {
        const server: ServerResource = {
            id: 1,
            name: 'Project Alpha',
            description: null,
            starts_on: null,
            ends_on: null,
            created_by: 1,
        };
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ ok: true }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        const onDeleted = vi.fn();

        render(MemberDialog, {
            props: {
                server,
                members: [],
                invitations: [],
                canManage: true,
                onDeleted,
                onClose: vi.fn(),
            },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: '完全に削除' }),
        );
        const confirmationButtons = screen.getAllByRole('button', {
            name: '完全に削除',
        });
        await fireEvent.click(confirmationButtons.at(-1)!);

        await waitFor(() => expect(onDeleted).toHaveBeenCalledWith(1));
        expect(fetchMock.mock.calls[0][0]).toBe('/servers/1');
        expect(fetchMock.mock.calls[0][1].method).toBe('DELETE');
    });

    it('shows project information but not management actions to non-admins', () => {
        const server: ServerResource = {
            id: 1,
            name: 'Project Alpha',
            description: null,
            starts_on: null,
            ends_on: null,
            created_by: 1,
        };

        render(MemberDialog, {
            props: {
                server,
                members: [{ id: 1, name: 'Owner', email: 'owner@example.com' }],
                invitations: [],
                canManage: false,
                onClose: vi.fn(),
            },
        });

        expect(
            (screen.getByDisplayValue('Project Alpha') as HTMLInputElement)
                .disabled,
        ).toBe(true);
        expect(screen.queryByRole('button', { name: '招待' })).toBeNull();
        expect(screen.queryByRole('button', { name: '完全に削除' })).toBeNull();
    });
});
