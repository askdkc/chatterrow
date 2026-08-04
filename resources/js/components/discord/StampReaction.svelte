<script lang="ts">
    import {
        MAX_STAMP_TEXT_LENGTH,
        stampReactionStyle,
        stampReactionText,
    } from '@/lib/reactions';
    import { cn } from '@/lib/utils';

    type StampSize = 'picker' | 'reaction' | 'chip';

    const FONT_SIZE_CLASSES: Record<StampSize, readonly string[]> = {
        picker: ['text-[40px]', 'text-[22px]', 'text-[20px]', 'text-[18px]'],
        reaction: ['text-[20px]', 'text-[11px]', 'text-[10px]', 'text-[9px]'],
        chip: ['text-[17px]', 'text-[9px]', 'text-[8px]', 'text-[7px]'],
    };

    let {
        value,
        size = 'picker',
    }: {
        value: string;
        size?: StampSize;
    } = $props();

    const text = $derived(stampReactionText(value) ?? value);
    const stampStyle = $derived(stampReactionStyle(value));
    const characters = $derived(Array.from(text));
    const characterCount = $derived(
        Math.min(characters.length, MAX_STAMP_TEXT_LENGTH),
    );
    const fontSizeClass = $derived(
        FONT_SIZE_CLASSES[size][Math.max(characterCount, 1) - 1],
    );
    const lines = $derived.by(() => {
        if (characters.length <= 2) {
            return [text];
        }

        const splitAt = Math.ceil(characters.length / 2);

        return [
            characters.slice(0, splitAt).join(''),
            characters.slice(splitAt).join(''),
        ];
    });
    const accented = $derived(!stampStyle && text === 'すごい');
</script>

<span
    data-stamp-reaction
    data-stamp-text={text}
    data-stamp-character-count={characterCount}
    data-stamp-text-color={stampStyle?.textColor}
    data-stamp-background-color={stampStyle
        ? (stampStyle.backgroundColor ?? 'transparent')
        : undefined}
    style:color={stampStyle?.textColor}
    style:background-color={stampStyle
        ? (stampStyle.backgroundColor ?? 'transparent')
        : undefined}
    class={cn(
        'inline-flex shrink-0 -rotate-2 items-center justify-center overflow-hidden rounded-sm border font-black',
        stampStyle
            ? stampStyle.backgroundColor === null
                ? 'border-transparent'
                : 'border-foreground/20'
            : accented
              ? 'border-transparent bg-transparent text-brand'
              : 'border-foreground/20 bg-foreground text-background',
        size === 'picker'
            ? 'size-12'
            : size === 'reaction'
              ? 'size-[22px]'
              : 'size-5',
    )}
    aria-hidden="true"
>
    <span
        data-stamp-glyphs
        class={cn(
            fontSizeClass,
            'flex size-full flex-col items-center justify-center tracking-[-0.08em] leading-[0.78]',
        )}
    >
        {#each lines as line, index (`${index}:${line}`)}
            <span>{line}</span>
        {/each}
    </span>
</span>
