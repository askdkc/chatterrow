<script module lang="ts">
    import { edit as editAppearance } from '@/routes/appearance';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Appearance settings',
                href: editAppearance(),
            },
        ],
    };
</script>

<script lang="ts">
    import AppearanceTabs from '@/components/AppearanceTabs.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import LanguageSwitcher from '@/components/LanguageSwitcher.svelte';
    import { t } from '@/lib/i18n';
</script>

<AppHead title={t('Appearance settings')} />

<h1 class="sr-only">{t('Appearance settings')}</h1>

<div class="space-y-6">
    <Heading
        variant="small"
        title={t('Appearance settings')}
        description={t('Update the appearance settings for your account')}
    />
    <LanguageSwitcher class="w-full sm:max-w-xs" />
    <AppearanceTabs />
</div>
