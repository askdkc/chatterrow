import { describe, expect, it, vi } from 'vitest';
import { filesFromDrop } from './dropped-files';

function fileEntry(file: File): FileSystemFileEntry {
    return {
        isFile: true,
        isDirectory: false,
        name: file.name,
        fullPath: `/${file.name}`,
        filesystem: {} as FileSystem,
        getParent: vi.fn(),
        file: vi.fn((resolve) => resolve(file)),
    };
}

function directoryEntry(
    entries: FileSystemEntry[],
    name = 'folder',
): FileSystemDirectoryEntry {
    let read = false;

    return {
        isFile: false,
        isDirectory: true,
        name,
        fullPath: `/${name}`,
        filesystem: {} as FileSystem,
        getParent: vi.fn(),
        getDirectory: vi.fn(),
        getFile: vi.fn(),
        createReader: vi.fn(() => ({
            readEntries: (resolve: FileSystemEntriesCallback) => {
                resolve(read ? [] : entries);
                read = true;
            },
        })),
    };
}

describe('filesFromDrop', () => {
    it('recursively extracts files from dropped folders', async () => {
        const first = new File(['first'], 'first.txt');
        const second = new File(['second'], 'second.txt');
        const root = directoryEntry([
            fileEntry(first),
            directoryEntry([fileEntry(second)]),
        ]);
        const dataTransfer = {
            items: [{ webkitGetAsEntry: () => root }],
            files: [],
        } as unknown as DataTransfer;

        await expect(filesFromDrop(dataTransfer)).resolves.toEqual([
            first,
            second,
        ]);
    });

    it('excludes hidden files from dropped folders', async () => {
        const visible = new File(['visible'], 'visible.txt');
        const hiddenEntries = [
            fileEntry(new File(['metadata'], '.DS_Store')),
            fileEntry(new File(['environment'], '.env')),
            fileEntry(new File(['ignore'], '.gitignore')),
            fileEntry(new File(['resource'], '._resource')),
        ];
        const root = directoryEntry([fileEntry(visible), ...hiddenEntries]);
        const dataTransfer = {
            items: [{ webkitGetAsEntry: () => root }],
            files: [],
        } as unknown as DataTransfer;

        await expect(filesFromDrop(dataTransfer)).resolves.toEqual([visible]);

        for (const entry of hiddenEntries) {
            expect(entry.file).not.toHaveBeenCalled();
        }
    });

    it('skips hidden directories without reading their contents', async () => {
        const visible = new File(['visible'], 'visible.txt');
        const hiddenDirectory = directoryEntry(
            [fileEntry(new File(['secret'], 'secret.txt'))],
            '.config',
        );
        const root = directoryEntry([
            hiddenDirectory,
            directoryEntry([fileEntry(visible)], 'documents'),
        ]);
        const dataTransfer = {
            items: [{ webkitGetAsEntry: () => root }],
            files: [],
        } as unknown as DataTransfer;

        await expect(filesFromDrop(dataTransfer)).resolves.toEqual([visible]);
        expect(hiddenDirectory.createReader).not.toHaveBeenCalled();
    });

    it('filters hidden files from the regular file list fallback', async () => {
        const visible = new File(['file'], 'file.txt');
        const dataTransfer = {
            items: [],
            files: [
                new File(['metadata'], '.DS_Store'),
                new File(['environment'], '.env'),
                visible,
            ],
        } as unknown as DataTransfer;

        await expect(filesFromDrop(dataTransfer)).resolves.toEqual([visible]);
    });

    it('returns an empty list when a drop contains only hidden entries', async () => {
        const root = directoryEntry([
            fileEntry(new File(['metadata'], '.DS_Store')),
            directoryEntry(
                [fileEntry(new File(['config'], 'settings.json'))],
                '.config',
            ),
        ]);
        const dataTransfer = {
            items: [{ webkitGetAsEntry: () => root }],
            files: [],
        } as unknown as DataTransfer;

        await expect(filesFromDrop(dataTransfer)).resolves.toEqual([]);
    });

    it('falls back to the regular file list', async () => {
        const file = new File(['file'], 'file.txt');
        const dataTransfer = {
            items: [],
            files: [file],
        } as unknown as DataTransfer;

        await expect(filesFromDrop(dataTransfer)).resolves.toEqual([file]);
    });
});
