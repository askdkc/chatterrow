<script lang="ts" module>
    import type { Snippet } from 'svelte';
    import type { HTMLButtonAttributes } from 'svelte/elements';

    export type ButtonVariant =
        | 'default'
        | 'secondary'
        | 'ghost'
        | 'destructive'
        | 'outline'
        | 'link';
    export type ButtonSize = 'default' | 'sm' | 'lg' | 'icon';
    export type ButtonAsChildProps = {
        class?: string;
        onClick?: (event: MouseEvent) => void;
        [key: string]: any;
    };
    export type ButtonProps = Omit<
        HTMLButtonAttributes,
        'children' | 'onclick' | 'type'
    > & {
        ref?: HTMLButtonElement | null;
        children?: Snippet<[ButtonAsChildProps]>;
        asChild?: boolean;
        variant?: ButtonVariant;
        size?: ButtonSize;
        type?: 'button' | 'submit' | 'reset';
        onclick?: any;
    };

    const base =
        'inline-flex items-center justify-center gap-2 rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 [&>svg]:size-4 [&>svg]:shrink-0 [&>svg]:pointer-events-none';

    const variants: Record<ButtonVariant, string> = {
        default: 'bg-primary text-primary-foreground shadow hover:bg-primary/90',
        secondary:
            'bg-secondary text-secondary-foreground shadow-sm hover:bg-secondary/80',
        ghost: 'hover:bg-accent hover:text-accent-foreground',
        destructive:
            'bg-destructive text-destructive-foreground shadow hover:bg-destructive/90',
        outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
        link: 'text-primary underline-offset-4 hover:underline',
    };

    const sizes: Record<ButtonSize, string> = {
        default: 'h-9 px-4 py-2',
        sm: 'h-8 rounded-md px-3 text-xs',
        lg: 'h-10 rounded-md px-8',
        icon: 'h-9 w-9',
    };

    export function buttonVariants({
        variant = 'default',
        size = 'default',
    }: {
        variant?: ButtonVariant;
        size?: ButtonSize;
    } = {}): string {
        return `${base} ${variants[variant]} ${sizes[size]}`;
    }
</script>

<script lang="ts">
    import { cn } from '@/lib/utils';

    let {
        ref = $bindable(null),
        children,
        asChild = false,
        variant = 'default',
        size = 'default',
        class: className = '',
        type = 'button',
        ...rest
    }: ButtonProps = $props();

    const classes = () => cn(buttonVariants({ variant, size }), className);
</script>

{#if asChild}
    {@render children?.({ class: classes(), ...rest })}
{:else}
    <button bind:this={ref} class={classes()} type={type} {...rest}>
        {@render children?.({})}
    </button>
{/if}
