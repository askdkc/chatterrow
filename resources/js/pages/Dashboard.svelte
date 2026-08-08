<script module lang="ts">
    import { index as serversIndex } from '@/routes/servers';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Project list',
                href: serversIndex(),
            },
        ],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import PlaceholderPattern from '@/components/PlaceholderPattern.svelte';
    import { t } from '@/lib/i18n';
</script>

<AppHead title={t('Project list')} />

<div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
    <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <div
            class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
        <div
            class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
        <div
            class="relative aspect-video overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border"
        >
            <PlaceholderPattern />
        </div>
    </div>
    <div
        class="relative min-h-screen flex-1 rounded-xl border border-sidebar-border/70 md:min-h-min dark:border-sidebar-border"
    >
        <PlaceholderPattern />
    </div>
</div>
