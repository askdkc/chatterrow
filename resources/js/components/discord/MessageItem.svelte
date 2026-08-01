<script lang="ts">
    import { MessageSquare, Paperclip, Download } from 'lucide-svelte';
    import type { MessageResource, StoredFileResource } from '@/types';

    let {
        message,
        onOpenThread,
    }: {
        message: MessageResource;
        onOpenThread: () => void;
    } = $props();

    function formatTime(iso: string | undefined): string {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
    }

    function formatDate(iso: string | undefined): string {
        if (!iso) return '';
        const d = new Date(iso);
        return d.toLocaleDateString('ja-JP', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    function isImage(file: StoredFileResource): boolean {
        return (file.mime_type ?? '').startsWith('image/');
    }

    function isVideo(file: StoredFileResource): boolean {
        return (file.mime_type ?? '').startsWith('video/');
    }

    function isPdf(file: StoredFileResource): boolean {
        return file.mime_type === 'application/pdf' || file.original_name.toLowerCase().endsWith('.pdf');
    }
</script>

<div class="group relative flex gap-4 rounded-md px-2 py-2 transition hover:bg-white/5">
    <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-[#5865f2] text-sm font-bold text-white"
    >
        {message.user?.name?.slice(0, 1).toUpperCase() ?? '?'}
    </div>
    <div class="min-w-0 flex-1">
        <div class="flex items-baseline gap-2">
            <span class="text-[15px] font-semibold text-[#dbdee1]">{message.user?.name ?? '不明'}</span>
            <span class="text-xs text-[#80848e]">{formatDate(message.created_at)} {formatTime(message.created_at)}</span>
        </div>
        <p class="whitespace-pre-wrap break-words text-[15px] text-[#dbdee1]">{message.body}</p>

        {#if message.attachments && message.attachments.length > 0}
            <div class="mt-2 flex flex-wrap gap-2">
                {#each message.attachments as file (file.id)}
                    {#if isImage(file)}
                        <a
                            href={file.stream_url ?? '#'}
                            target="_blank"
                            rel="noopener"
                            class="block max-w-72 overflow-hidden rounded-lg"
                        >
                            <img
                                src={file.stream_url}
                                alt={file.original_name}
                                class="max-h-64 w-auto rounded-lg object-cover transition hover:opacity-90"
                                loading="lazy"
                            />
                        </a>
                    {:else if isVideo(file)}
                        <video
                            src={file.stream_url}
                            controls
                            preload="metadata"
                            class="max-h-64 max-w-72 rounded-lg"
                        ></video>
                    {:else}
                        <a
                            href={file.download_url ?? '#'}
                            class="flex items-center gap-2 rounded-lg bg-[#383a40] px-3 py-2 text-sm transition hover:bg-[#404249]"
                        >
                            <Paperclip class="h-4 w-4 shrink-0" />
                            <span class="max-w-48 truncate">{file.original_name}</span>
                            <Download class="h-3.5 w-3.5 shrink-0 opacity-60" />
                        </a>
                    {/if}
                {/each}
            </div>
        {/if}

        {#if onOpenThread}
            <button
                type="button"
                class="mt-1.5 flex items-center gap-1.5 rounded px-1.5 py-0.5 text-xs font-medium text-[#80848e] transition hover:bg-white/10 hover:text-[#dbdee1]"
                onclick={onOpenThread}
            >
                <MessageSquare class="h-3.5 w-3.5" />
                スレッド
                {#if message.reply_count}
                    <span>{message.reply_count}</span>
                {/if}
            </button>
        {/if}
    </div>
</div>
