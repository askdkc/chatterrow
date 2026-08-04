<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { HTMLAttributes } from 'svelte/elements';
    import { cn } from '@/lib/utils';

    let {
        class: className = '',
        children,
        ...rest
    }: Omit<HTMLAttributes<HTMLDivElement>, 'children'> & {
        children?: Snippet;
    } = $props();
</script>

<div
    class={cn('relative flex h-10 w-10 shrink-0 overflow-hidden rounded-full', className)}
    {...rest}
>
    {@render children?.()}
</div>
