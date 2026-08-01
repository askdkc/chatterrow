import { cleanup, fireEvent, render } from '@testing-library/svelte';
import { afterEach, describe, expect, it, vi } from 'vitest';
import StoredFilePreviewDialog from './StoredFilePreviewDialog.svelte';

afterEach(cleanup);

describe('StoredFilePreviewDialog', () => {
    it('closes when Escape is pressed', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(() => new Promise(() => undefined)),
        );
        const onClose = vi.fn();

        render(StoredFilePreviewDialog, {
            props: {
                serverId: 1,
                file: { id: 2, name: 'report.pdf' },
                onClose,
            },
        });

        await fireEvent.keyDown(window, { key: 'Escape' });

        expect(onClose).toHaveBeenCalledOnce();
    });
});
