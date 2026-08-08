<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import LanguageSwitcher from '@/components/LanguageSwitcher.svelte';
    import { t } from '@/lib/i18n';
    import { home } from '@/routes';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div
    class="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
>
    <div
        class="absolute top-4 right-4 w-36 max-w-[calc(100vw-2rem)] sm:top-6 sm:right-6 sm:w-40"
    >
        <LanguageSwitcher />
    </div>
    <div class="w-full max-w-sm">
        <div class="flex flex-col gap-8">
            <div class="flex flex-col items-center gap-4">
                <Link
                    href={home()}
                    class="flex flex-col items-center gap-2 font-medium"
                >
                    <div class="mb-1 size-12 overflow-hidden rounded-xl">
                        <AppLogoIcon class="size-12" />
                    </div>
                    <span class="sr-only">{t(title)}</span>
                </Link>
                <div class="space-y-2 text-center">
                    <h1 class="text-xl font-medium">{t(title)}</h1>
                    <p class="text-center text-sm text-muted-foreground">
                        {t(description)}
                    </p>
                </div>
            </div>
            {@render children?.()}
        </div>
    </div>
</div>
