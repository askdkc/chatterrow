<script lang="ts">
    import Download from 'lucide-svelte/icons/download';
    import FileText from 'lucide-svelte/icons/file-text';
    import X from 'lucide-svelte/icons/x';
    import { Button } from '@/components/ui/button';
    import {
        Dialog,
        DialogContent,
        DialogDescription,
        DialogTitle,
    } from '@/components/ui/dialog';
    import { t } from '@/lib/i18n';

    let {
        name,
        url,
        onClose,
    }: {
        name: string;
        url: string | null;
        onClose: () => void;
    } = $props();
</script>

<Dialog open={url !== null} onOpenChange={(isOpen) => !isOpen && onClose()}>
    <DialogContent
        class="flex h-[calc(100dvh-1rem)] w-[calc(100vw-1rem)] max-w-[96rem] flex-col gap-0 overflow-hidden rounded-2xl p-0 sm:h-[calc(100dvh-3rem)] sm:w-[calc(100vw-3rem)]"
    >
        <header class="flex items-start gap-3 border-b px-4 py-3 sm:px-5">
            <div class="min-w-0 flex-1">
                <DialogTitle class="truncate text-base sm:text-lg">
                    {name}
                </DialogTitle>
                <DialogDescription class="mt-1 text-xs sm:text-sm">
                    {t(
                        'PDF, Office and CSV files are shown here without downloading.',
                    )}
                </DialogDescription>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                {#if url}
                    <Button
                        variant="ghost"
                        size="icon"
                        aria-label={t('Download')}
                        asChild
                    >
                        {#snippet children(buttonProps)}
                            <a
                                {...buttonProps}
                                href={url}
                                download={name}
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
            {#if url}
                <iframe
                    src={url}
                    title={name}
                    class="h-full w-full border-0"
                    loading="eager"
                ></iframe>
            {:else}
                <div
                    class="flex h-full items-center justify-center bg-background p-6 text-center"
                >
                    <div>
                        <FileText
                            class="mx-auto size-10 text-muted-foreground"
                        />
                        <p class="mt-3 font-semibold">
                            {t('Preparing preview...')}
                        </p>
                    </div>
                </div>
            {/if}
        </div>
    </DialogContent>
</Dialog>
