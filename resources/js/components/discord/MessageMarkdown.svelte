<script lang="ts">
    import { Check, Copy } from 'lucide-svelte';
    import { onDestroy } from 'svelte';
    import { Button } from '@/components/ui/button';
    import { t } from '@/lib/i18n';
    import {
        renderHighlightedMessageMarkdownParts,
        renderMessageMarkdownParts,
    } from '@/lib/markdown';
    import type { RenderedMessagePart } from '@/lib/markdown';
    import type { MentionResource } from '@/types';

    let {
        value,
        mentions = [],
        currentUserId,
    }: {
        value: string;
        mentions?: readonly MentionResource[];
        currentUserId?: number | null;
    } = $props();

    let highlighted = $state<{
        source: string;
        parts: RenderedMessagePart[];
    } | null>(null);
    let copyState = $state<{
        index: number;
        status: 'copied' | 'error';
    } | null>(null);
    let renderGeneration = 0;
    let copyFeedbackTimer: ReturnType<typeof setTimeout> | undefined;
    const fallbackParts = $derived(
        renderMessageMarkdownParts(value, mentions, currentUserId),
    );
    const renderedParts = $derived(
        highlighted?.source === value ? highlighted.parts : fallbackParts,
    );

    $effect(() => {
        const source = value;
        const activeMentions = mentions;
        const activeUserId = currentUserId;
        const generation = ++renderGeneration;
        highlighted = null;

        void renderHighlightedMessageMarkdownParts(
            source,
            activeMentions,
            activeUserId,
        ).then((parts) => {
            if (generation === renderGeneration) {
                highlighted = { source, parts };
            }
        });
    });

    onDestroy(() => {
        renderGeneration += 1;

        if (copyFeedbackTimer) {
            clearTimeout(copyFeedbackTimer);
        }
    });

    function languageLabel(language: string): string {
        return language === 'text' ? 'TEXT' : language.toLocaleUpperCase();
    }

    function copyButtonLabel(index: number): string {
        if (copyState?.index !== index) {
            return t('Copy code');
        }

        return copyState.status === 'copied'
            ? t('Code copied')
            : t('Could not copy code');
    }

    function copyButtonText(index: number): string {
        if (copyState?.index !== index) {
            return t('Copy');
        }

        return copyState.status === 'copied' ? t('Copied') : t('Retry');
    }

    async function writeToClipboard(code: string): Promise<void> {
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(code);

            return;
        }

        const textarea = document.createElement('textarea');
        textarea.value = code;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.append(textarea);
        textarea.select();
        const copied = document.execCommand('copy');
        textarea.remove();

        if (!copied) {
            throw new Error('Clipboard copy failed');
        }
    }

    async function copyCode(code: string, index: number) {
        if (copyFeedbackTimer) {
            clearTimeout(copyFeedbackTimer);
        }

        try {
            await writeToClipboard(code);
            copyState = { index, status: 'copied' };
        } catch {
            copyState = { index, status: 'error' };
        }

        copyFeedbackTimer = setTimeout(() => {
            if (copyState?.index === index) {
                copyState = null;
            }
        }, 2000);
    }
</script>

{#each renderedParts as part, index (index)}
    {#if part.kind === 'html'}
        <!-- Markdown rendering escapes user-controlled HTML before this point. -->
        <!-- eslint-disable-next-line svelte/no-at-html-tags -->
        {@html part.html}
    {:else}
        <div
            data-code-block
            data-code-language={part.language}
            class="my-1.5 overflow-hidden rounded-md border border-border bg-code-block"
        >
            <div
                data-code-toolbar
                class="flex min-h-9 items-center justify-between gap-2 border-b border-border bg-muted/50 px-2 py-1"
            >
                <span
                    class="font-mono text-[11px] font-medium tracking-wide text-muted-foreground"
                >
                    {languageLabel(part.language)}
                </span>
                <Button
                    variant="ghost"
                    size="sm"
                    aria-label={copyButtonLabel(index)}
                    title={copyButtonLabel(index)}
                    onclick={() => copyCode(part.code, index)}
                >
                    {#if copyState?.index === index && copyState.status === 'copied'}
                        <Check data-icon="inline-start" />
                    {:else}
                        <Copy data-icon="inline-start" />
                    {/if}
                    {copyButtonText(index)}
                </Button>
            </div>
            <div data-code-content>
                <!-- Shiki output contains escaped source and generated spans only. -->
                <!-- eslint-disable-next-line svelte/no-at-html-tags -->
                {@html part.html}
            </div>
        </div>
    {/if}
{/each}
