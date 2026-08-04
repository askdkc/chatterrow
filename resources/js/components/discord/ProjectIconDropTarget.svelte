<script lang="ts">
    import Upload from 'lucide-svelte/icons/upload';
    import ProjectIcon from '@/components/discord/ProjectIcon.svelte';
    import { cn } from '@/lib/utils';
    import type { ServerResource } from '@/types';

    let {
        server,
        disabled = false,
        onChoose,
        onFile,
    }: {
        server: Pick<ServerResource, 'name' | 'icon_url'>;
        disabled?: boolean;
        onChoose?: () => void;
        onFile: (file: File) => void;
    } = $props();

    let dragging = $state(false);

    function containsFiles(event: DragEvent): boolean {
        return event.dataTransfer?.types.includes('Files') ?? false;
    }

    function handleDragEnter(event: DragEvent) {
        if (disabled || !containsFiles(event)) {
            return;
        }

        event.preventDefault();
        dragging = true;
    }

    function handleDragOver(event: DragEvent) {
        if (disabled || !containsFiles(event)) {
            return;
        }

        event.preventDefault();

        if (event.dataTransfer) {
            event.dataTransfer.dropEffect = 'copy';
        }

        dragging = true;
    }

    function handleDragLeave(event: DragEvent) {
        const nextTarget = event.relatedTarget;

        if (
            nextTarget instanceof Node &&
            event.currentTarget instanceof Node &&
            event.currentTarget.contains(nextTarget)
        ) {
            return;
        }

        dragging = false;
    }

    function handleDrop(event: DragEvent) {
        if (disabled) {
            return;
        }

        event.preventDefault();
        dragging = false;

        const file = event.dataTransfer?.files[0];

        if (file) {
            onFile(file);
        }
    }
</script>

<button
    type="button"
    class={cn(
        'relative shrink-0 rounded-xl outline-none transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background disabled:cursor-not-allowed disabled:opacity-60',
        dragging && 'ring-2 ring-primary ring-offset-2 ring-offset-background',
    )}
    aria-label="プロジェクトアイコン画像を選択またはドロップ"
    title={disabled
        ? undefined
        : 'クリックして選択、または画像をドラッグ＆ドロップ'}
    {disabled}
    onclick={onChoose}
    ondragenter={handleDragEnter}
    ondragover={handleDragOver}
    ondragleave={handleDragLeave}
    ondrop={handleDrop}
>
    <ProjectIcon {server} size="preview" />

    {#if dragging}
        <span
            class="absolute inset-0 flex flex-col items-center justify-center gap-0.5 rounded-xl bg-primary/90 text-[9px] font-semibold text-primary-foreground"
        >
            <Upload class="size-4" />
            ドロップ
        </span>
    {/if}
</button>
