import type * as InertiaSvelte from '@inertiajs/svelte';
import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type * as HttpModule from '@/lib/http';
import type { ProjectFolderResource, ServerResource } from '@/types';
import Index from './Index.svelte';

const inertia = vi.hoisted(() => ({
    visit: vi.fn(),
    reload: vi.fn(),
    props: {
        auth: {
            user: { id: 1 },
            servers: [],
        },
    },
}));

const http = vi.hoisted(() => ({
    apiJson: vi.fn(),
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<typeof InertiaSvelte>();

    return {
        ...actual,
        router: {
            ...actual.router,
            visit: inertia.visit,
            reload: inertia.reload,
        },
        usePage: () => inertia,
    };
});

vi.mock('@/lib/http', async (importOriginal) => {
    const actual = await importOriginal<typeof HttpModule>();

    return {
        ...actual,
        apiJson: http.apiJson,
    };
});

const folders: ProjectFolderResource[] = [
    { id: 10, name: 'クライアント案件', position: 1 },
];

const servers: ServerResource[] = [
    {
        id: 1,
        name: 'Project Alpha',
        description: null,
        icon_url: '/servers/1/icon?v=test',
        starts_on: null,
        ends_on: null,
        created_by: 1,
        project_folder_id: 10,
        channels_count: 2,
        members_count: 1,
        members: [
            {
                id: 1,
                name: 'Owner',
                email: 'owner@example.com',
                pivot: { role: 'admin' },
            },
        ],
    },
    {
        id: 2,
        name: 'Project Beta',
        description: null,
        starts_on: null,
        ends_on: null,
        created_by: 2,
        project_folder_id: null,
        channels_count: 1,
        members_count: 2,
        members: [
            {
                id: 1,
                name: 'Member',
                email: 'member@example.com',
                pivot: { role: 'member' },
            },
        ],
    },
];

function renderIndex() {
    return render(Index, {
        props: {
            servers: structuredClone(servers),
            folders: structuredClone(folders),
            archivedCount: 3,
            invitations: [],
        },
    });
}

function dataTransfer(): DataTransfer {
    const values = new Map<string, string>();

    return {
        dropEffect: 'none',
        effectAllowed: 'uninitialized',
        files: [] as unknown as FileList,
        items: [] as unknown as DataTransferItemList,
        types: [],
        clearData: (format?: string) => {
            if (format) {
                values.delete(format);
            } else {
                values.clear();
            }
        },
        getData: (format: string) => values.get(format) ?? '',
        setData: (format: string, value: string) => {
            values.set(format, value);
        },
        setDragImage: vi.fn(),
    } as DataTransfer;
}

beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=token-123; Path=/; Max-Age=3600';
    http.apiJson.mockReset();
    inertia.visit.mockReset();
    inertia.reload.mockReset();
});

afterEach(cleanup);

describe('project folders on the project index', () => {
    it('groups projects and exposes folder creation and archived projects', () => {
        renderIndex();

        expect(
            screen.getByRole('button', { name: 'フォルダを作成' }),
        ).toBeTruthy();
        expect(
            screen.getByRole('heading', { name: 'クライアント案件' }),
        ).toBeTruthy();
        expect(screen.getByRole('heading', { name: '未分類' })).toBeTruthy();
        expect(screen.getByText('Project Alpha')).toBeTruthy();
        expect(screen.getByText('Project Beta')).toBeTruthy();
        expect(
            document.querySelector('img[src="/servers/1/icon?v=test"]'),
        ).toBeTruthy();

        const archivedLink = screen.getByRole('link', {
            name: /アーカイブ済み 3/,
        });
        expect(archivedLink.getAttribute('href')).toBe('/servers/archived');
    });

    it('creates a folder from the header dialog', async () => {
        http.apiJson.mockResolvedValue({
            folder: {
                id: 11,
                name: '新しいフォルダ',
                color: '#5865F2',
                icon_url: null,
                position: 2,
            },
        });
        renderIndex();

        await fireEvent.click(
            screen.getByRole('button', { name: 'フォルダを作成' }),
        );
        await fireEvent.input(screen.getByLabelText('フォルダ名'), {
            target: { value: '新しいフォルダ' },
        });
        await fireEvent.click(screen.getByRole('button', { name: '作成' }));

        await waitFor(() => expect(http.apiJson).toHaveBeenCalled());
        const [url, init] = http.apiJson.mock.calls.at(-1) ?? [];
        const form = init?.body as FormData;

        expect(url).toBe('/project-folders');
        expect(init?.method).toBe('POST');
        expect(form.get('name')).toBe('新しいフォルダ');
        expect(form.get('color')).toBe('#5865F2');
        expect(
            screen.getByRole('heading', { name: '新しいフォルダ' }),
        ).toBeTruthy();
    });

    it('moves a project to a folder without requiring project admin rights', async () => {
        http.apiJson.mockResolvedValue({
            server_id: 2,
            project_folder_id: 10,
        });
        const { container } = renderIndex();

        await fireEvent.click(
            screen.getByRole('button', {
                name: 'Project Betaをフォルダへ移動',
            }),
        );
        await fireEvent.click(
            screen.getByRole('button', { name: 'クライアント案件' }),
        );

        await waitFor(() =>
            expect(http.apiJson).toHaveBeenCalledWith('/servers/2/folder', {
                method: 'PATCH',
                body: JSON.stringify({ project_folder_id: 10 }),
            }),
        );

        expect(
            container.querySelector('[data-folder-drop-zone="10"]')
                ?.textContent,
        ).toContain('Project Beta');
    });

    it('moves a project by dragging its card onto a folder', async () => {
        http.apiJson.mockResolvedValue({
            server_id: 2,
            project_folder_id: 10,
        });
        const { container } = renderIndex();
        const card = container.querySelector('[data-project-card="2"]');
        const folderDropZone = container.querySelector(
            '[data-folder-drop-zone="10"]',
        );
        const transfer = dataTransfer();

        expect(card).toBeTruthy();
        expect(folderDropZone).toBeTruthy();

        await fireEvent.dragStart(card as Element, {
            dataTransfer: transfer,
        });
        await fireEvent.dragOver(folderDropZone as Element, {
            dataTransfer: transfer,
        });

        expect(screen.getByText('ここにドロップ')).toBeTruthy();

        await fireEvent.drop(folderDropZone as Element, {
            dataTransfer: transfer,
        });

        await waitFor(() =>
            expect(http.apiJson).toHaveBeenCalledWith('/servers/2/folder', {
                method: 'PATCH',
                body: JSON.stringify({ project_folder_id: 10 }),
            }),
        );
        await waitFor(() =>
            expect(
                container.querySelector('[data-folder-drop-zone="10"]')
                    ?.textContent,
            ).toContain('Project Beta'),
        );
    });
});
