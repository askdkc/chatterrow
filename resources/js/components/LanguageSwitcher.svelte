<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import ChevronDown from 'lucide-svelte/icons/chevron-down';
    import Globe2 from 'lucide-svelte/icons/globe-2';
    import { t } from '@/lib/i18n';
    import { cn } from '@/lib/utils';

    let {
        compact = false,
        dark = false,
        class: className = '',
    }: {
        compact?: boolean;
        dark?: boolean;
        class?: string;
    } = $props();

    const locale = $derived(page.props.locale ?? 'en');
    const locales = $derived(page.props.locales ?? ['en']);
    const localeNames = $derived(page.props.localeNames ?? {});
    const currentName = $derived(localeNames[locale] ?? locale);

    function switchLanguage(event: Event) {
        const nextLocale = (event.currentTarget as HTMLSelectElement).value;

        if (!nextLocale || nextLocale === locale) {
            return;
        }

        window.location.assign(`/language/${encodeURIComponent(nextLocale)}`);
    }
</script>

<div
    class={cn(
        'relative flex h-9 min-w-0 items-center gap-2 rounded-md border text-sm transition-colors focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2',
        compact ? 'w-10 justify-center' : 'w-full',
        dark
            ? 'border-white/10 bg-[#313338] text-[#dbdee1] hover:bg-white/10'
            : 'border-input bg-background text-foreground hover:bg-accent',
        className,
    )}
    title={currentName}
    data-language-switcher
>
    <Globe2
        class={cn(
            'pointer-events-none size-4 shrink-0 opacity-70',
            !compact && 'ml-2',
        )}
    />
    {#if compact}
        <span class="sr-only">{currentName}</span>
    {:else}
        <span class="min-w-0 flex-1 truncate">{currentName}</span>
    {/if}
    {#if !compact}
        <ChevronDown
            class="pointer-events-none mr-2 size-4 shrink-0 opacity-50"
        />
    {/if}
    <select
        value={locale}
        onchange={switchLanguage}
        aria-label={t('Language')}
        class="absolute inset-0 h-full w-full cursor-pointer appearance-none opacity-0"
    >
        {#each locales as availableLocale (availableLocale)}
            <option value={availableLocale}>
                {localeNames[availableLocale] ?? availableLocale}
            </option>
        {/each}
    </select>
</div>
