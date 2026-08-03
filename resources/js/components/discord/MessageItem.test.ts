import {
    cleanup,
    fireEvent,
    render,
    screen,
    waitFor,
} from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import type { MessageResource } from '@/types';
import MessageItem from './MessageItem.svelte';

afterEach(cleanup);

describe('MessageItem attachments', () => {
    it('opens an image in the centered file preview dialog', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => new Promise(() => undefined)),
        );
        const message: MessageResource = {
            id: 1,
            server_id: 2,
            channel_id: 3,
            user_id: 4,
            parent_id: null,
            body: 'image',
            created_at: '2026-08-01T12:00:00Z',
            user: { id: 4, name: 'Test User', email: 'test@example.com' },
            attachments: [
                {
                    id: 5,
                    server_id: 2,
                    path: 'uploads/image.png',
                    original_name: 'image.png',
                    mime_type: 'image/png',
                    size: 100,
                    preview_status: null,
                    stream_url: '/servers/2/files/5/stream',
                    download_url: '/servers/2/files/5/download',
                },
            ],
        };

        render(MessageItem, { props: { message } });
        await fireEvent.click(
            screen.getByRole('button', { name: 'image.pngをプレビュー' }),
        );

        expect(screen.getByRole('dialog')).toBeTruthy();
        expect(screen.getByText('image.png')).toBeTruthy();
    });

    it('shows a PDF thumbnail and closes its preview with Escape', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => new Promise(() => undefined)),
        );
        const message: MessageResource = {
            id: 1,
            server_id: 2,
            channel_id: 3,
            user_id: 4,
            parent_id: null,
            body: 'pdf',
            created_at: '2026-08-01T12:00:00Z',
            attachments: [
                {
                    id: 6,
                    server_id: 2,
                    path: 'uploads/report.pdf',
                    original_name: 'report.pdf',
                    mime_type: 'application/pdf',
                    size: 200,
                    preview_status: 'ready',
                    thumbnail_url: '/servers/2/files/6/thumbnail',
                    stream_url: '/servers/2/files/6/stream',
                    download_url: '/servers/2/files/6/download',
                },
            ],
        };

        render(MessageItem, { props: { message } });

        expect(
            screen.getByRole('img', { name: 'report.pdf' }).getAttribute('src'),
        ).toBe('/servers/2/files/6/thumbnail');
        expect(screen.getByText('PDF')).toBeTruthy();
        await fireEvent.click(
            screen.getByRole('button', { name: 'report.pdfをプレビュー' }),
        );
        expect(screen.getByRole('dialog')).toBeTruthy();

        await fireEvent.keyDown(window, { key: 'Escape' });
        await waitFor(() => expect(screen.queryByRole('dialog')).toBeNull());
    });

    it('opens Office files with the OnlyOffice viewer', async () => {
        const fetchMock = vi.fn(() => new Promise(() => undefined));
        vi.stubGlobal('fetch', fetchMock);
        const message: MessageResource = {
            id: 1,
            server_id: 2,
            channel_id: 3,
            user_id: 4,
            parent_id: null,
            body: 'office',
            created_at: '2026-08-01T12:00:00Z',
            attachments: [
                {
                    id: 7,
                    server_id: 2,
                    path: 'uploads/report.docx',
                    original_name: 'report.docx',
                    mime_type:
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    size: 300,
                    preview_status: 'ready',
                    thumbnail_url: '/servers/2/files/7/thumbnail',
                    stream_url: '/servers/2/files/7/stream',
                    download_url: '/servers/2/files/7/download',
                },
            ],
        };

        render(MessageItem, { props: { message } });
        await fireEvent.click(
            screen.getByRole('button', { name: 'report.docxをプレビュー' }),
        );

        expect(screen.getByText('DOCX')).toBeTruthy();
        expect(screen.getByRole('dialog')).toBeTruthy();
        await waitFor(() =>
            expect(fetchMock).toHaveBeenCalledWith(
                '/servers/2/files/7/onlyoffice/config',
                expect.objectContaining({ credentials: 'same-origin' }),
            ),
        );
    });
});
