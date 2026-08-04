<script lang="ts">
    import { cn } from '@/lib/utils';
    import type { ServerResource } from '@/types';

    let {
        server,
        size = 'card',
        initialsLength = 2,
        class: className,
    }: {
        server: Pick<ServerResource, 'name' | 'icon_url'>;
        size?: 'compact' | 'card' | 'rail' | 'preview' | 'hero';
        initialsLength?: 1 | 2;
        class?: string;
    } = $props();

    const sizeClass = $derived(
        size === 'compact'
            ? 'size-8 rounded-lg text-xs'
            : size === 'preview'
              ? 'size-14 rounded-xl text-lg'
              : size === 'hero'
                ? 'size-20 rounded-3xl text-2xl'
                : size === 'rail'
                  ? 'size-12 rounded-2xl text-sm group-hover:rounded-xl'
                  : 'size-12 rounded-xl text-base',
    );
</script>

<span
    class={cn(
        'flex shrink-0 items-center justify-center overflow-hidden bg-primary font-bold text-primary-foreground transition-all',
        sizeClass,
        className,
    )}
    data-project-icon
    aria-hidden="true"
>
    {#if server.icon_url}
        <img
            src={server.icon_url}
            alt=""
            class="size-full object-cover"
            draggable="false"
        />
    {:else}
        {server.name.slice(0, initialsLength).toUpperCase()}
    {/if}
</span>
