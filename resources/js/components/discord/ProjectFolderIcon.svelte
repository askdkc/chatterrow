<script lang="ts">
    import Folder from 'lucide-svelte/icons/folder';
    import { cn } from '@/lib/utils';
    import type { ProjectFolderResource } from '@/types';

    let {
        folder,
        size = 'sm',
        class: className,
    }: {
        folder: Pick<ProjectFolderResource, 'name' | 'color' | 'icon_url'>;
        size?: 'sm' | 'rail' | 'preview';
        class?: string;
    } = $props();

    const displayColor = $derived(
        /^#[0-9a-f]{6}$/i.test(folder.color ?? '') ? folder.color! : '#B5BAC1',
    );
    const sizeClass = $derived(
        size === 'rail'
            ? 'size-12 rounded-2xl group-hover/folder:rounded-xl'
            : size === 'preview'
              ? 'size-14 rounded-xl'
              : 'size-7 rounded-lg',
    );
    const iconSizeClass = $derived(
        size === 'rail' ? 'size-5' : size === 'preview' ? 'size-6' : 'size-4',
    );
</script>

<span
    class={cn(
        'flex shrink-0 items-center justify-center overflow-hidden transition-all',
        sizeClass,
        className,
    )}
    style:color={displayColor}
    style:background-color={`${displayColor}26`}
    data-project-folder-icon
    data-folder-color={displayColor}
    aria-hidden="true"
>
    {#if folder.icon_url}
        <img
            src={folder.icon_url}
            alt=""
            class="size-full object-cover"
            draggable="false"
        />
    {:else}
        <Folder class={iconSizeClass} />
    {/if}
</span>
