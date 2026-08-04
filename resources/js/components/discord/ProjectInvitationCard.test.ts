import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ProjectInvitationResource } from '@/types';
import ProjectInvitationCard from './ProjectInvitationCard.svelte';

afterEach(cleanup);

const invitation: ProjectInvitationResource = {
    id: 12,
    email: 'invitee@example.com',
    status: 'pending',
    registered: true,
    sent_at: '2026-08-04T10:00:00Z',
    responded_at: null,
    server: {
        id: 3,
        created_by: 1,
        name: 'Project Alpha',
        description: 'Invitation test',
    },
    inviter: {
        id: 1,
        name: 'Owner',
        email: 'owner@example.com',
    },
};

describe('ProjectInvitationCard', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=token-123; Path=/; Max-Age=3600';
        vi.restoreAllMocks();
    });

    it('accepts a project invitation', async () => {
        const server = {
            id: 3,
            created_by: 1,
            name: 'Project Alpha',
            description: 'Invitation test',
            starts_on: null,
            ends_on: null,
        };
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ server }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        const onAccepted = vi.fn();

        render(ProjectInvitationCard, {
            props: { invitation, onAccepted },
        });

        expect(screen.getByText('Project Alpha')).toBeTruthy();
        expect(screen.getByText(/Ownerさんから/)).toBeTruthy();
        await fireEvent.click(screen.getByRole('button', { name: '参加する' }));

        await waitFor(() => expect(onAccepted).toHaveBeenCalledWith(server));
        expect(fetchMock.mock.calls[0][0]).toBe(
            '/project-invitations/12/accept',
        );
        expect(fetchMock.mock.calls[0][1].method).toBe('PATCH');
    });

    it('declines a project invitation', async () => {
        const fetchMock = vi.fn().mockResolvedValue(
            new Response(JSON.stringify({ ok: true }), {
                status: 200,
                headers: { 'Content-Type': 'application/json' },
            }),
        );
        vi.stubGlobal('fetch', fetchMock);
        const onDeclined = vi.fn();

        render(ProjectInvitationCard, {
            props: { invitation, onDeclined },
        });

        await fireEvent.click(
            screen.getByRole('button', { name: '参加しない' }),
        );

        await waitFor(() => expect(onDeclined).toHaveBeenCalledWith(12));
        expect(fetchMock.mock.calls[0][0]).toBe(
            '/project-invitations/12/decline',
        );
    });
});
