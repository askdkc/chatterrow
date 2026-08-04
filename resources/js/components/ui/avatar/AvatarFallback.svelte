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
    class={cn(
        'flex h-full w-full items-center justify-center rounded-full bg-muted text-sm font-medium text-muted-foreground',
        className,
    )}
    {...rest}
>
    {@render children?.()}
</div>
