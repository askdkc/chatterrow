<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';

    let {
        title = '',
        children,
    }: {
        title?: string;
        children?: Snippet;
    } = $props();

    const appName = $derived(page.props.name ?? 'Chatterrow');
    const fullTitle = $derived(title ? `${title} - ${appName}` : appName);
</script>

<svelte:head>
    <title>{fullTitle}</title>
    {@render children?.()}
</svelte:head>
