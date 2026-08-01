<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import KeyRound from 'lucide-svelte/icons/key-round';
    import { destroy } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyRegistrationController';
    import Heading from '@/components/Heading.svelte';
    import PasskeyItem from '@/components/PasskeyItem.svelte';
    import PasskeyRegister from '@/components/PasskeyRegister.svelte';
    import { t } from '@/lib/i18n';
    import type { Passkey } from '@/types/auth';

    export type Props = {
        canManagePasskeys?: boolean;
        passkeys?: Passkey[];
    };

    let { canManagePasskeys = false, passkeys = [] }: Props = $props();

    const handleDelete = (id: number, onError: () => void) => {
        router.delete(destroy.url(id), {
            preserveScroll: true,
            onError,
        });
    };

    const handleRegisterSuccess = () => {
        router.reload();
    };
</script>

{#if canManagePasskeys}
    <div class="space-y-6">
        <Heading
            variant="small"
            title={t('Passkeys')}
            description={t('Manage your passkeys for passwordless sign-in')}
        />

        <div class="overflow-hidden rounded-lg border border-border">
            {#if passkeys.length > 0}
                {#each passkeys as passkey (passkey.id)}
                    <PasskeyItem {passkey} onDelete={handleDelete} />
                {/each}
            {:else}
                <div class="p-8 text-center">
                    <div
                        class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-muted"
                    >
                        <KeyRound class="h-7 w-7 text-muted-foreground" />
                    </div>
                    <p class="font-medium">{t('No passkeys yet')}</p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {t('Add a passkey to sign in without a password')}
                    </p>
                </div>
            {/if}
        </div>

        <PasskeyRegister onSuccess={handleRegisterSuccess} />
    </div>
{/if}
