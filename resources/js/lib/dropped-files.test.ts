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
        file: (resolve) => resolve(file),
    };
}

function directoryEntry(entries: FileSystemEntry[]): FileSystemDirectoryEntry {
    let read = false;

    return {
        isFile: false,
        isDirectory: true,
        name: 'folder',
        fullPath: '/folder',
        filesystem: {} as FileSystem,
        getParent: vi.fn(),
        getDirectory: vi.fn(),
        getFile: vi.fn(),
        createReader: () => ({
            readEntries: (resolve) => {
                resolve(read ? [] : entries);
                read = true;
            },
        }),
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

    it('falls back to the regular file list', async () => {
        const file = new File(['file'], 'file.txt');
        const dataTransfer = {
            items: [],
            files: [file],
        } as unknown as DataTransfer;

        await expect(filesFromDrop(dataTransfer)).resolves.toEqual([file]);
    });
});
