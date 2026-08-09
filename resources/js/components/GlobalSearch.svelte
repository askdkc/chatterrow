<script lang="ts">
    import { FileText, Loader2, MessageSquare, Search, X } from 'lucide-svelte';
    import { onMount } from 'svelte';
    import { Button } from '@/components/ui/button';
    import { formatDateTime } from '@/lib/dates';
    import { apiJson, HttpError } from '@/lib/http';
    import { t } from '@/lib/i18n';
    import type { GlobalSearchResult } from '@/types';

    let { class: className = '' }: { class?: string } = $props();

    let query = $state('');
    let results = $state<GlobalSearchResult[]>([]);
    let loading = $state(false);
    let error = $state('');
    let mobileOpen = $state(false);
    let showResults = $state(false);
    let activeIndex = $state(-1);
    let searchInput = $state<HTMLInputElement>();
    let searchTimer: ReturnType<typeof setTimeout> | undefined;
    let requestController: AbortController | undefined;
    let requestSequence = 0;

    const messageResults = $derived(
        results.filter((result) => result.type === 'message'),
    );
    const fileResults = $derived(
        results.filter((result) => result.type === 'file'),
    );

    onMount(() => {
        return () => {
            if (searchTimer) {
                clearTimeout(searchTimer);
            }

            requestController?.abort();
        };
    });

    function resultIndex(result: GlobalSearchResult): number {
        return results.indexOf(result);
    }

    function handleInput() {
        const value = query.trim();

        requestSequence += 1;
        requestController?.abort();
        requestController = undefined;

        if (searchTimer) {
            clearTimeout(searchTimer);
        }

        activeIndex = -1;
        error = '';

        if (!value) {
            loading = false;
            results = [];
            showResults = false;

            return;
        }

        loading = true;
        showResults = true;
        const sequence = requestSequence;

        searchTimer = setTimeout(() => {
            void search(value, sequence);
        }, 300);
    }

    async function search(value: string, sequence: number) {
        const controller = new AbortController();
        requestController = controller;

        try {
            const data = await apiJson<{
                results: GlobalSearchResult[];
            }>(`/search?q=${encodeURIComponent(value)}`, {
                signal: controller.signal,
            });

            if (sequence !== requestSequence) {
                return;
            }

            results = data.results;
            activeIndex = -1;
        } catch (exception) {
            if (
                sequence !== requestSequence ||
                (exception instanceof Error && exception.name === 'AbortError')
            ) {
                return;
            }

            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : t('Search could not be completed');
            results = [];
        } finally {
            if (sequence === requestSequence) {
                loading = false;
            }

            if (requestController === controller) {
                requestController = undefined;
            }
        }
    }

    function openMobileSearch() {
        mobileOpen = true;
        showResults = query.trim() !== '';
        requestAnimationFrame(() => searchInput?.focus());
    }

    function closeMobileSearch() {
        mobileOpen = false;
        searchInput?.blur();
    }

    function handleSearchKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            event.preventDefault();
            showResults = false;
            activeIndex = -1;

            if (mobileOpen) {
                closeMobileSearch();
            }

            return;
        }

        if (event.key === 'ArrowDown' && results.length > 0) {
            event.preventDefault();
            activeIndex = (activeIndex + 1) % results.length;
        }

        if (event.key === 'ArrowUp' && results.length > 0) {
            event.preventDefault();
            activeIndex =
                activeIndex <= 0 ? results.length - 1 : activeIndex - 1;
        }

        if (event.key === 'Enter' && activeIndex >= 0) {
            event.preventDefault();
            openResult(results[activeIndex]);
        }
    }

    function openResult(result: GlobalSearchResult) {
        window.location.assign(result.url);
    }

    function resultLabel(result: GlobalSearchResult): string {
        return result.type === 'message'
            ? t('Message in :channel', {
                  channel: result.channel?.name ?? t('Unknown channel'),
              })
            : t('Document :name', { name: result.original_name ?? t('File') });
    }
</script>

<div class={`relative min-w-0 ${className}`}>
    <div class="hidden min-w-0 md:block">
        <label class="sr-only" for="global-search-desktop">
            {t('Search all projects')}
        </label>
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <input
                id="global-search-desktop"
                bind:this={searchInput}
                bind:value={query}
                type="search"
                role="combobox"
                autocomplete="off"
                placeholder={t('Search projects...')}
                aria-label={t('Search all projects')}
                aria-expanded={showResults}
                aria-controls="global-search-results"
                class="h-9 w-[min(20rem,35vw)] rounded-md border border-border bg-background/80 pr-3 pl-9 text-sm text-foreground shadow-sm outline-none transition focus-visible:ring-2 focus-visible:ring-ring"
                oninput={handleInput}
                onkeydown={handleSearchKeydown}
            />
        </div>
    </div>

    <Button
        variant="ghost"
        size="icon"
        class="md:hidden"
        aria-label={t('Open global search')}
        title={t('Search all projects')}
        onclick={openMobileSearch}
    >
        <Search />
    </Button>

    {#if showResults}
        <div
            id="global-search-results"
            role="region"
            aria-label={t('Search results')}
            class="absolute top-[calc(100%+0.5rem)] right-0 z-50 hidden max-h-[min(32rem,calc(100vh-5rem))] w-[min(34rem,calc(100vw-2rem))] overflow-y-auto rounded-xl border border-border bg-popover p-2 text-popover-foreground shadow-xl md:block"
        >
            {#if loading}
                <div
                    class="flex items-center gap-2 px-3 py-4 text-sm text-muted-foreground"
                >
                    <Loader2 class="size-4 animate-spin" />
                    {t('Searching...')}
                </div>
            {:else if error}
                <p class="px-3 py-4 text-sm text-destructive" role="alert">
                    {error}
                </p>
            {:else if results.length === 0}
                <p class="px-3 py-4 text-sm text-muted-foreground">
                    {t('No results found')}
                </p>
            {:else}
                {#if messageResults.length > 0}
                    <h2
                        class="px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground"
                    >
                        {t('Messages')}
                    </h2>
                    {#each messageResults as result (result.type + result.id)}
                        {@const index = resultIndex(result)}
                        <button
                            type="button"
                            role="option"
                            aria-selected={activeIndex === index}
                            class={`flex w-full items-start gap-3 rounded-lg px-3 py-2 text-left transition ${activeIndex === index ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/70'}`}
                            onclick={() => openResult(result)}
                            onmouseenter={() => (activeIndex = index)}
                        >
                            <MessageSquare
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span class="truncate"
                                        >{result.server.name}</span
                                    >
                                    {#if result.channel}
                                        <span class="truncate"
                                            >#{result.channel.name}</span
                                        >
                                    {/if}
                                </span>
                                <span class="mt-1 block text-sm leading-5">
                                    {#each result.snippet as segment, segmentIndex (segmentIndex)}
                                        <span
                                            class={segment.type === 'hit'
                                                ? 'rounded-sm bg-amber-300/40 font-semibold'
                                                : ''}
                                        >
                                            {segment.text}
                                        </span>
                                    {/each}
                                </span>
                                <span
                                    class="mt-1 block truncate text-xs text-muted-foreground"
                                >
                                    {result.author?.name ?? t('Unknown author')} ·
                                    {formatDateTime(result.created_at, {
                                        fallback: t('Unknown date'),
                                    })}
                                </span>
                            </span>
                            <span class="sr-only">{resultLabel(result)}</span>
                        </button>
                    {/each}
                {/if}

                {#if fileResults.length > 0}
                    <h2
                        class="mt-2 px-3 pt-2 pb-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground"
                    >
                        {t('Documents')}
                    </h2>
                    {#each fileResults as result (result.type + result.id)}
                        {@const index = resultIndex(result)}
                        <button
                            type="button"
                            role="option"
                            aria-selected={activeIndex === index}
                            class={`flex w-full items-start gap-3 rounded-lg px-3 py-2 text-left transition ${activeIndex === index ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/70'}`}
                            onclick={() => openResult(result)}
                            onmouseenter={() => (activeIndex = index)}
                        >
                            <FileText
                                class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                            />
                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span
                                        class="truncate font-medium text-foreground"
                                        >{result.original_name}</span
                                    >
                                    <span class="truncate"
                                        >{result.server.name}</span
                                    >
                                    {#if result.channel}
                                        <span class="truncate"
                                            >#{result.channel.name}</span
                                        >
                                    {/if}
                                </span>
                                <span class="mt-1 block text-sm leading-5">
                                    {#each result.snippet as segment, segmentIndex (segmentIndex)}
                                        <span
                                            class={segment.type === 'hit'
                                                ? 'rounded-sm bg-amber-300/40 font-semibold'
                                                : ''}
                                        >
                                            {segment.text}
                                        </span>
                                    {/each}
                                </span>
                                <span
                                    class="mt-1 block truncate text-xs text-muted-foreground"
                                >
                                    {formatDateTime(result.created_at, {
                                        fallback: t('Unknown date'),
                                    })}
                                </span>
                            </span>
                            <span class="sr-only">{resultLabel(result)}</span>
                        </button>
                    {/each}
                {/if}
            {/if}
        </div>
    {/if}
</div>

{#if mobileOpen}
    <div
        class="fixed inset-0 z-[60] flex items-start justify-center bg-black/60 p-4 pt-[10vh] md:hidden"
    >
        <button
            type="button"
            class="fixed inset-0 cursor-default"
            aria-label={t('Close search')}
            onclick={closeMobileSearch}
        ></button>
        <div
            class="relative z-10 flex max-h-[80vh] w-full max-w-lg flex-col overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-2xl"
            role="dialog"
            aria-modal="true"
            aria-label={t('Search all projects')}
        >
            <div class="flex items-center gap-2 border-b border-border p-3">
                <Search class="size-4 shrink-0 text-muted-foreground" />
                <label class="sr-only" for="global-search-mobile">
                    {t('Search all projects')}
                </label>
                <input
                    id="global-search-mobile"
                    bind:this={searchInput}
                    bind:value={query}
                    type="search"
                    autocomplete="off"
                    placeholder={t('Search projects...')}
                    aria-label={t('Search all projects')}
                    class="min-w-0 flex-1 bg-transparent text-sm outline-none"
                    oninput={handleInput}
                    onkeydown={handleSearchKeydown}
                />
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={t('Close search')}
                    onclick={closeMobileSearch}
                >
                    <X />
                </Button>
            </div>
            <div
                class="min-h-24 overflow-y-auto p-2"
                role="region"
                aria-label={t('Search results')}
            >
                {#if loading}
                    <div
                        class="flex items-center gap-2 px-3 py-4 text-sm text-muted-foreground"
                    >
                        <Loader2 class="size-4 animate-spin" />
                        {t('Searching...')}
                    </div>
                {:else if error}
                    <p class="px-3 py-4 text-sm text-destructive" role="alert">
                        {error}
                    </p>
                {:else if query.trim() === ''}
                    <p class="px-3 py-4 text-sm text-muted-foreground">
                        {t('Type to search all projects')}
                    </p>
                {:else if results.length === 0}
                    <p class="px-3 py-4 text-sm text-muted-foreground">
                        {t('No results found')}
                    </p>
                {:else}
                    {#each results as result (result.type + result.id)}
                        {@const index = resultIndex(result)}
                        <button
                            type="button"
                            class={`flex w-full items-start gap-3 rounded-lg px-3 py-2 text-left transition ${activeIndex === index ? 'bg-accent text-accent-foreground' : 'hover:bg-accent/70'}`}
                            onclick={() => openResult(result)}
                            onmouseenter={() => (activeIndex = index)}
                        >
                            {#if result.type === 'message'}
                                <MessageSquare
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                            {:else}
                                <FileText
                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                />
                            {/if}
                            <span class="min-w-0 flex-1">
                                <span
                                    class="flex items-center gap-2 text-xs text-muted-foreground"
                                >
                                    <span class="truncate"
                                        >{result.server.name}</span
                                    >
                                    {#if result.channel}
                                        <span class="truncate"
                                            >#{result.channel.name}</span
                                        >
                                    {/if}
                                </span>
                                {#if result.type === 'file'}
                                    <span
                                        class="mt-0.5 block truncate text-sm font-medium"
                                        >{result.original_name}</span
                                    >
                                {/if}
                                <span class="mt-1 block text-sm leading-5">
                                    {#each result.snippet as segment, segmentIndex (segmentIndex)}
                                        <span
                                            class={segment.type === 'hit'
                                                ? 'rounded-sm bg-amber-300/40 font-semibold'
                                                : ''}
                                        >
                                            {segment.text}
                                        </span>
                                    {/each}
                                </span>
                            </span>
                        </button>
                    {/each}
                {/if}
            </div>
        </div>
    </div>
{/if}
