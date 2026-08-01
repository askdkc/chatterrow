<script module lang="ts">
    const previewableExtensions = new Set([
        'pdf',
        'doc',
        'docx',
        'odt',
        'xls',
        'xlsx',
        'xlsm',
        'ods',
        'ppt',
        'pptx',
        'odp',
        'csv',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'mp4',
        'webm',
        'mov',
        'm4v',
    ]);

    export const canPreviewStoredFile = (name: string): boolean =>
        previewableExtensions.has(
            name.split('.').pop()?.toLocaleLowerCase('en-US') ?? '',
        );
</script>

<script lang="ts">
    import type { FileViewerElement } from '@file-viewer/web';
    import Download from 'lucide-svelte/icons/download';
    import FileText from 'lucide-svelte/icons/file-text';
    import LoaderCircle from 'lucide-svelte/icons/loader-circle';
    import X from 'lucide-svelte/icons/x';
    import { onDestroy, tick } from 'svelte';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { t } from '@/lib/i18n';

    type PreviewFile = {
        id: number;
        name: string;
        mimeType?: string | null;
    };

    let {
        serverId,
        file,
        onClose,
    }: {
        serverId: number;
        file: PreviewFile | null;
        onClose: () => void;
    } = $props();

    let previewLoading = $state(false);
    let previewError = $state('');
    let previewHost = $state<HTMLDivElement>();
    let previewRequest: AbortController | null = null;
    let previewViewer: FileViewerElement | null = null;
    let nativePreviewUrl = $state<string | null>(null);
    let nativePreviewKind = $state<'image' | 'video' | null>(null);
    let loadedFileId: number | null = null;

    const streamUrl = $derived(
        file ? `/servers/${serverId}/files/${file.id}/stream` : '',
    );
    const downloadUrl = $derived(
        file ? `/servers/${serverId}/files/${file.id}/download` : '',
    );

    const cleanupPreview = (): void => {
        previewRequest?.abort();
        previewRequest = null;
        previewViewer?.destroy();
        previewViewer = null;

        if (nativePreviewUrl) {
            URL.revokeObjectURL(nativePreviewUrl);
            nativePreviewUrl = null;
        }

        nativePreviewKind = null;
        // eslint-disable-next-line svelte/no-dom-manipulating
        previewHost?.replaceChildren();
        previewLoading = false;
        loadedFileId = null;
    };

    const loadPreview = async (previewFile: PreviewFile): Promise<void> => {
        cleanupPreview();
        loadedFileId = previewFile.id;
        previewError = '';
        previewLoading = true;

        const request = new AbortController();
        previewRequest = request;

        await tick();

        try {
            const response = await fetch(streamUrl, {
                credentials: 'same-origin',
                headers: { Accept: 'application/octet-stream' },
                signal: request.signal,
            });

            if (!response.ok) {
                throw new Error(`Preview request failed: ${response.status}`);
            }

            const blob = await response.blob();
            const extension = previewFile.name
                .split('.')
                .pop()
                ?.toLocaleLowerCase('en-US');

            if (['jpg', 'jpeg', 'png', 'webp'].includes(extension ?? '')) {
                nativePreviewKind = 'image';
                nativePreviewUrl = URL.createObjectURL(blob);

                return;
            }

            if (['mp4', 'webm', 'mov', 'm4v'].includes(extension ?? '')) {
                nativePreviewKind = 'video';
                nativePreviewUrl = URL.createObjectURL(blob);

                return;
            }

            if (request.signal.aborted || !previewHost) {
                return;
            }

            const [web, preset] = await Promise.all([
                import('@file-viewer/web'),
                import('@file-viewer/preset-office'),
            ]);

            web.defineFileViewerElement();

            const viewer = document.createElement(
                web.FILE_VIEWER_ELEMENT_TAG,
            ) as FileViewerElement;
            viewer.style.cssText =
                'display:block;width:100%;height:100%;min-height:0';
            viewer.options = {
                preset: preset.default,
                rendererMode: 'replace',
                locale: 'ja-JP',
                theme: 'system',
                styleIsolation: 'shadow',
                pdf: {
                    assetBaseUrl: '/file-viewer/',
                },
                toolbar: {
                    position: 'bottom-right',
                    download: false,
                    exportHtml: false,
                },
            };
            const isConvertedPdf =
                extension !== 'pdf' &&
                (response.headers.get('content-type') ?? '').startsWith(
                    'application/pdf',
                );
            viewer.file = new File(
                [blob],
                isConvertedPdf ? `${previewFile.name}.pdf` : previewFile.name,
                {
                    type: isConvertedPdf
                        ? 'application/pdf'
                        : blob.type ||
                          previewFile.mimeType ||
                          'application/octet-stream',
                },
            );
            viewer.addEventListener('viewer-error', () => {
                if (previewViewer === viewer) {
                    previewError = t('Could not load this file preview.');
                }
            });

            previewViewer = viewer;
            // eslint-disable-next-line svelte/no-dom-manipulating
            previewHost.replaceChildren(viewer);
        } catch (error) {
            if (!(
                error instanceof DOMException && error.name === 'AbortError'
            )) {
                previewError = t('Could not load this file preview.');
            }
        } finally {
            if (previewRequest === request) {
                previewRequest = null;
                previewLoading = false;
            }
        }
    };

    $effect(() => {
        if (file && loadedFileId !== file.id) {
            void loadPreview(file);
        }
    });

    onDestroy(cleanupPreview);
</script>

<Dialog open={file !== null} onOpenChange={(isOpen) => !isOpen && onClose()}>
    <DialogContent
        class="flex h-[calc(100dvh-1rem)] w-[calc(100vw-1rem)] max-w-[96rem] flex-col gap-0 overflow-hidden rounded-2xl p-0 sm:h-[calc(100dvh-3rem)] sm:w-[calc(100vw-3rem)]"
    >
        <header class="flex items-start gap-3 border-b px-4 py-3 sm:px-5">
            <div class="min-w-0 flex-1">
                <DialogTitle class="truncate text-base sm:text-lg">
                    {file?.name ?? t('Preview')}
                </DialogTitle>
                <DialogDescription class="mt-1 text-xs sm:text-sm">
                    {t(
                        'PDF, Office and CSV files are shown here without downloading.',
                    )}
                </DialogDescription>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                {#if file}
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('Download')}
                        asChild
                    >
                        {#snippet children(buttonProps)}
                            <a
                                {...buttonProps}
                                href={downloadUrl}
                                class={buttonProps.class}
                            >
                                <Download class="size-5" />
                            </a>
                        {/snippet}
                    </Button>
                {/if}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('Close')}
                    onclick={onClose}
                >
                    <X class="size-5" />
                </Button>
            </div>
        </header>

        <div class="relative min-h-0 flex-1 overflow-hidden bg-slate-100">
            {#if previewLoading}
                <div
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-3 bg-background"
                    role="status"
                >
                    <LoaderCircle class="size-8 animate-spin text-primary" />
                    <p class="text-sm font-medium">
                        {t('Preparing preview...')}
                    </p>
                </div>
            {:else if previewError}
                <div
                    class="absolute inset-0 z-10 flex items-center justify-center bg-background p-6 text-center"
                    role="alert"
                >
                    <div>
                        <FileText
                            class="mx-auto size-10 text-muted-foreground"
                        />
                        <p class="mt-3 font-semibold">{previewError}</p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {t(
                                'Use the download button if the file is damaged.',
                            )}
                        </p>
                    </div>
                </div>
            {/if}

            {#if nativePreviewKind === 'image' && nativePreviewUrl}
                <div
                    class="flex h-full items-center justify-center overflow-auto p-4"
                >
                    <img
                        src={nativePreviewUrl}
                        alt={file?.name ?? t('Image preview')}
                        class="max-h-full max-w-full object-contain"
                    />
                </div>
            {:else if nativePreviewKind === 'video' && nativePreviewUrl}
                <div
                    class="flex h-full items-center justify-center bg-black p-2"
                >
                    <video
                        src={nativePreviewUrl}
                        controls
                        class="max-h-full max-w-full"
                    >
                        <track kind="captions" />
                    </video>
                </div>
            {:else}
                <div
                    bind:this={previewHost}
                    class="h-full w-full"
                    aria-label={t('Document preview')}
                ></div>
            {/if}
        </div>
    </DialogContent>
</Dialog>
