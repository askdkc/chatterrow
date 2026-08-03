import { cleanup, fireEvent, render, screen } from '@testing-library/svelte';
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
});
