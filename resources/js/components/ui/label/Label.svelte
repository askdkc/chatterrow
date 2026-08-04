<script lang="ts">
    import type { Snippet } from 'svelte';
    import type { HTMLLabelAttributes } from 'svelte/elements';
    import { cn } from '@/lib/utils';

    type Props = HTMLLabelAttributes & {
        ref?: HTMLLabelElement | null;
        children?: Snippet;
    };

    let {
        ref = $bindable(null),
        class: className = '',
        children,
        ...rest
    }: Props = $props();
</script>

<label
    bind:this={ref}
    class={cn(
        'text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70',
        className,
    )}
    {...rest}
>
    {@render children?.()}
</label>
