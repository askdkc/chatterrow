<script lang="ts">
    import { t } from '@/lib/i18n';
    import { Link } from '@inertiajs/svelte';
    import {
        Breadcrumb,
        BreadcrumbItem,
        BreadcrumbLink,
        BreadcrumbList,
        BreadcrumbPage,
        BreadcrumbSeparator,
    } from '@/components/ui/breadcrumb';
    import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

    let {
        breadcrumbs = [],
    }: {
        breadcrumbs: BreadcrumbItemType[];
    } = $props();
</script>

<Breadcrumb>
    <BreadcrumbList>
        {#each breadcrumbs as item, index (item.href)}
            <BreadcrumbItem>
                {#if index === breadcrumbs.length - 1}
                    <BreadcrumbPage>{t(item.title)}</BreadcrumbPage>
                {:else}
                    <BreadcrumbLink asChild>
                        {#snippet children(props)}
                            <Link href={item.href} class={props.class}>
                                {t(item.title)}
                            </Link>
                        {/snippet}
                    </BreadcrumbLink>
                {/if}
            </BreadcrumbItem>
            {#if index !== breadcrumbs.length - 1}
                <BreadcrumbSeparator />
            {/if}
        {/each}
    </BreadcrumbList>
</Breadcrumb>
