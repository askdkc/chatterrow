async function readDirectoryEntries(
    reader: FileSystemDirectoryReader,
): Promise<FileSystemEntry[]> {
    const entries: FileSystemEntry[] = [];

    while (true) {
        const batch = await new Promise<FileSystemEntry[]>(
            (resolve, reject) => {
                reader.readEntries(resolve, reject);
            },
        );

        if (batch.length === 0) {
            return entries;
        }

        entries.push(...batch);
    }
}

async function filesFromEntry(entry: FileSystemEntry): Promise<File[]> {
    if (entry.isFile) {
        return new Promise<File[]>((resolve, reject) => {
            (entry as FileSystemFileEntry).file(
                (file) => resolve([file]),
                reject,
            );
        });
    }

    if (!entry.isDirectory) {
        return [];
    }

    const entries = await readDirectoryEntries(
        (entry as FileSystemDirectoryEntry).createReader(),
    );
    const files = await Promise.all(entries.map(filesFromEntry));

    return files.flat();
}

export async function filesFromDrop(
    dataTransfer: DataTransfer,
): Promise<File[]> {
    const entries = Array.from(dataTransfer.items)
        .map((item) => item.webkitGetAsEntry?.() ?? null)
        .filter((entry): entry is FileSystemEntry => entry !== null);

    if (entries.length === 0) {
        return Array.from(dataTransfer.files);
    }

    const files = await Promise.all(entries.map(filesFromEntry));

    return files.flat();
}
