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
import type { ServerResource } from '@/types';
import Files from './Files.svelte';

const inertia = vi.hoisted(() => ({
    props: {
        auth: {
            servers: [],
        },
    },
}));

const http = vi.hoisted(() => ({
    apiFetch: vi.fn(),
    apiJson: vi.fn(),
}));

vi.mock('@inertiajs/svelte', async (importOriginal) => {
    const actual = await importOriginal<typeof InertiaSvelte>();

    return {
        ...actual,
        usePage: () => inertia,
    };
});

vi.mock('@/lib/http', async (importOriginal) => {
    const actual = await importOriginal<typeof HttpModule>();

    return {
        ...actual,
        apiFetch: http.apiFetch,
        apiJson: http.apiJson,
    };
});

const server: ServerResource = {
    id: 1,
    name: 'Test Server',
    description: null,
    starts_on: null,
    ends_on: null,
    created_by: 1,
    channels: [],
};

interface FileProps {
    id: number;
    original_name: string;
    mime_type: string | null;
    size: number | null;
    preview_status: string | null;
    created_at: string | null;
    stream_url: string;
    download_url: string;
    thumbnail_url: string | null;
}

function renderFiles(files: FileProps[] = []) {
    return render(Files, {
        props: {
            server,
            channel: null,
            files,
            channels: [],
            members: [],
        },
    });
}

function fileDrop(files: File[]): DataTransfer {
    return {
        types: ['Files'],
        items: [],
        files,
        dropEffect: 'none',
    } as unknown as DataTransfer;
}

function uploadedFiles(callIndex: number): File[] {
    const init = http.apiFetch.mock.calls[callIndex]?.[1] as
        RequestInit | undefined;
    const form = init?.body as FormData;

    return form.getAll('files[]') as File[];
}

beforeEach(() => {
    http.apiFetch.mockReset();
    http.apiJson.mockReset();
});

afterEach(cleanup);

describe('Files previews', () => {
    it('shows file-type badges on document preview cards', () => {
        renderFiles([
            {
                id: 8,
                original_name: 'report.xlsx',
                mime_type:
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                size: 1024,
                preview_status: 'ready',
                created_at: '2026-08-04T12:00:00+09:00',
                stream_url: '/servers/1/files/8/stream',
                download_url: '/servers/1/files/8/download',
                thumbnail_url: '/servers/1/files/8/thumbnail',
            },
            {
                id: 9,
                original_name: 'specification.pdf',
                mime_type: 'application/pdf',
                size: 2048,
                preview_status: 'ready',
                created_at: '2026-08-04T12:00:00+09:00',
                stream_url: '/servers/1/files/9/stream',
                download_url: '/servers/1/files/9/download',
                thumbnail_url: '/servers/1/files/9/thumbnail',
            },
        ]);

        expect(screen.getByTitle('XLSXファイル')).toBeTruthy();
        expect(screen.getByTitle('PDFファイル')).toBeTruthy();
        expect(
            document.querySelector('[data-file-type-icon="spreadsheet"]'),
        ).toBeTruthy();
        expect(
            document.querySelector('[data-file-type-icon="pdf"]'),
        ).toBeTruthy();
    });

    it('uses the streamed video frame as the file-card thumbnail', () => {
        renderFiles([
            {
                id: 7,
                original_name: 'clip.mp4',
                mime_type: 'video/mp4',
                size: 1024,
                preview_status: null,
                created_at: '2026-08-04T12:00:00+09:00',
                stream_url: '/servers/1/files/7/stream',
                download_url: '/servers/1/files/7/download',
                thumbnail_url: null,
            },
        ]);

        const previewButton = screen.getByRole('button', {
            name: 'clip.mp4をプレビュー',
        });
        const video = previewButton.querySelector('video');

        expect(video).not.toBeNull();
        expect(video?.getAttribute('src')).toBe('/servers/1/files/7/stream');
        expect(video?.preload).toBe('metadata');
        expect(video?.muted).toBe(true);
        expect(screen.queryByText('動画')).toBeNull();
    });
});

describe('Files drag and drop upload', () => {
    it('shows the drop overlay and uploads files dropped on the list area', async () => {
        http.apiFetch.mockReturnValue(new Promise(() => undefined));
        renderFiles();

        const dropRegion = screen.getByRole('region', {
            name: 'ファイル一覧とアップロードドロップ領域',
        });
        const report = new File(['report'], 'report.pdf', {
            type: 'application/pdf',
        });
        const dataTransfer = fileDrop([report]);

        await fireEvent.dragEnter(dropRegion, { dataTransfer });
        expect(screen.getByRole('status').textContent).toContain(
            'ドロップしてアップロード',
        );

        await fireEvent.dragOver(dropRegion, { dataTransfer });
        expect(dataTransfer.dropEffect).toBe('copy');

        await fireEvent.drop(dropRegion, { dataTransfer });

        await waitFor(() => expect(http.apiFetch).toHaveBeenCalledOnce());
        expect(http.apiFetch).toHaveBeenCalledWith('/servers/1/files', {
            method: 'POST',
            body: expect.any(FormData),
        });
        expect(uploadedFiles(0)).toEqual([report]);
        expect(screen.queryByRole('status')).toBeNull();
        expect(
            screen
                .getByRole('button', { name: 'アップロード' })
                .hasAttribute('disabled'),
        ).toBe(true);
    });

    it('removes the drop overlay when the dragged files leave the list area', async () => {
        renderFiles();

        const dropRegion = screen.getByRole('region', {
            name: 'ファイル一覧とアップロードドロップ領域',
        });
        const dataTransfer = fileDrop([new File(['file'], 'file.txt')]);

        await fireEvent.dragEnter(dropRegion, { dataTransfer });
        expect(screen.getByRole('status')).toBeTruthy();

        await fireEvent.dragLeave(dropRegion, { dataTransfer });
        expect(screen.queryByRole('status')).toBeNull();
    });

    it('uploads more than ten dropped files in API-sized batches', async () => {
        http.apiFetch
            .mockResolvedValueOnce(new Response('{}', { status: 201 }))
            .mockReturnValueOnce(new Promise(() => undefined));
        renderFiles();

        const dropRegion = screen.getByRole('region', {
            name: 'ファイル一覧とアップロードドロップ領域',
        });
        const files = Array.from(
            { length: 11 },
            (_, index) => new File([String(index)], `file-${index}.txt`),
        );

        await fireEvent.drop(dropRegion, {
            dataTransfer: fileDrop(files),
        });

        await waitFor(() => expect(http.apiFetch).toHaveBeenCalledTimes(2));
        expect(uploadedFiles(0)).toEqual(files.slice(0, 10));
        expect(uploadedFiles(1)).toEqual(files.slice(10));
    });
});
