import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ServerRail from './ServerRail.svelte';

afterEach(cleanup);

describe('ServerRail', () => {
    it('expands to show project names and collapses back to initials', async () => {
        const { container } = render(ServerRail, {
            props: {
                servers: [
                    {
                        id: 1,
                        name: 'デザインを固める',
                        description: null,
                        starts_on: null,
                        ends_on: null,
                        created_by: 1,
                    },
                    {
                        id: 2,
                        name: 'ファンドレイズ',
                        description: null,
                        starts_on: null,
                        ends_on: null,
                        created_by: 1,
                    },
                ],
                activeServerId: 1,
                onAddServer: vi.fn(),
                onBrowse: vi.fn(),
            },
        });

        expect(screen.queryByText('デザインを固める')).toBeNull();
        expect(
            container.querySelector('img[src="/chatterrow-icon.png"]'),
        ).toBeTruthy();

        await fireEvent.mouseEnter(screen.getByRole('navigation'));

        expect(screen.getByText('デザインを固める')).toBeTruthy();
        expect(screen.getByText('ファンドレイズ')).toBeTruthy();

        const activeProject = screen.getByText('デザインを固める').closest('a');
        expect(activeProject?.classList.contains('bg-[#5865f2]')).toBe(false);
        expect(activeProject?.classList.contains('bg-white/80')).toBe(true);

        const createButton = screen.getByRole('button', {
            name: 'プロジェクトを作成',
        });
        expect(createButton.classList.contains('w-48')).toBe(true);
        expect(createButton.classList.contains('self-center')).toBe(true);
        expect(createButton.classList.contains('justify-center')).toBe(true);

        expect(
            screen.queryByRole('button', {
                name: 'プロジェクト一覧を折りたたむ',
            }),
        ).toBeNull();

        await fireEvent.mouseLeave(screen.getByRole('navigation'));

        expect(screen.queryByText('デザインを固める')).toBeNull();
    });

    it('opens notifications beside the trigger and keeps the rail expanded', async () => {
        render(ServerRail, {
            props: {
                servers: [
                    {
                        id: 1,
                        name: 'デザインを固める',
                        description: null,
                        starts_on: null,
                        ends_on: null,
                        created_by: 1,
                    },
                ],
                activeServerId: 1,
                onAddServer: vi.fn(),
                onBrowse: vi.fn(),
            },
        });

        const rail = screen.getByRole('navigation');
        await fireEvent.mouseEnter(rail);
        await fireEvent.click(screen.getByRole('button', { name: '通知' }));

        const notificationList = await screen.findByRole('region', {
            name: '通知一覧',
        });
        const popover = notificationList.closest(
            '[data-slot="popover-content"]',
        );

        expect(popover?.getAttribute('data-side')).toBe('right');
        expect(popover?.getAttribute('data-align')).toBe('start');

        await fireEvent.mouseLeave(rail);
        expect(screen.getByText('デザインを固める')).toBeTruthy();

        await fireEvent.click(
            screen.getByRole('button', { name: '通知を閉じる' }),
        );
        expect(screen.queryByRole('region', { name: '通知一覧' })).toBeNull();
        expect(screen.queryByText('デザインを固める')).toBeNull();
    });

    it('opens notifications on hover and stays open while moving into the popover', async () => {
        render(ServerRail, {
            props: {
                servers: [],
                activeServerId: null,
                onAddServer: vi.fn(),
                onBrowse: vi.fn(),
            },
        });

        const trigger = screen.getByRole('button', { name: '通知' });
        await fireEvent.mouseEnter(trigger);

        const notificationList = screen.getByRole('region', {
            name: '通知一覧',
        });
        const hoverSurface = notificationList.closest(
            '[data-notification-hover-surface]',
        );

        expect(hoverSurface).toBeTruthy();

        await fireEvent.mouseLeave(trigger);
        expect(trigger.getAttribute('data-hover-close-pending')).toBe('true');
        await fireEvent.mouseEnter(hoverSurface as Element);
        expect(trigger.getAttribute('data-hover-close-pending')).toBe('false');

        expect(screen.getByRole('region', { name: '通知一覧' })).toBeTruthy();

        await fireEvent.mouseLeave(hoverSurface as Element);
        expect(trigger.getAttribute('data-hover-close-pending')).toBe('true');

        await waitFor(() =>
            expect(
                screen.queryByRole('region', { name: '通知一覧' }),
            ).toBeNull(),
        );
    });

    it('groups projects into folders and reveals one folder as a hover accordion', async () => {
        const { container } = render(ServerRail, {
            props: {
                servers: [
                    {
                        id: 1,
                        name: 'Project Alpha',
                        description: null,
                        icon_url: '/servers/1/icon?v=rail',
                        starts_on: null,
                        ends_on: null,
                        created_by: 1,
                        project_folder_id: 10,
                    },
                    {
                        id: 2,
                        name: 'Project Beta',
                        description: null,
                        starts_on: null,
                        ends_on: null,
                        created_by: 1,
                        project_folder_id: null,
                    },
                ],
                folders: [
                    {
                        id: 10,
                        name: 'クライアント案件',
                        color: '#FF5500',
                        icon_url: '/project-folders/10/icon',
                        position: 1,
                    },
                ],
                activeServerId: 1,
                onAddServer: vi.fn(),
                onBrowse: vi.fn(),
            },
        });

        const rail = screen.getByRole('navigation');
        await fireEvent.mouseEnter(rail);

        expect(screen.getByText('クライアント案件')).toBeTruthy();
        expect(screen.getByText('Project Beta')).toBeTruthy();
        expect(screen.queryByText('Project Alpha')).toBeNull();
        expect(
            container.querySelector('img[src="/project-folders/10/icon"]'),
        ).toBeTruthy();

        const folder = container.querySelector('[data-project-folder="10"]');
        expect(folder).toBeTruthy();
        await fireEvent.mouseEnter(folder as Element);

        expect(
            screen.getByRole('group', {
                name: 'クライアント案件内のプロジェクト',
            }),
        ).toBeTruthy();
        expect(screen.getByText('Project Alpha')).toBeTruthy();
        expect(
            container.querySelector('img[src="/servers/1/icon?v=rail"]'),
        ).toBeTruthy();

        await fireEvent.mouseLeave(folder as Element);
        expect(screen.queryByText('Project Alpha')).toBeNull();
    });
});
