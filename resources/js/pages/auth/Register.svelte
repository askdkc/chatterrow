<script module lang="ts">
    export const layout = {
        title: 'Create an account',
        description: 'Enter your details below to create your account',
    };
</script>

<script lang="ts">
    import { t } from '@/lib/i18n';
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { login } from '@/routes';
    import { store } from '@/routes/register';

    let { passwordRules }: { passwordRules: string } = $props();
</script>

<AppHead title={t('Register')} />

<Form
    {...store.form()}
    resetOnSuccess={['password', 'password_confirmation']}
    class="flex flex-col gap-6"
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">{t('Name')}</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autocomplete="name"
                    name="name"
                    placeholder={t('Full name')}
                />
                <InputError message={errors.name} />
            </div>

            <div class="grid gap-2">
                <Label for="email">{t('Email address')}</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError message={errors.email} />
            </div>

            <div class="grid gap-2">
                <Label for="password">{t('Password')}</Label>
                <PasswordInput
                    id="password"
                    required
                    autocomplete="new-password"
                    name="password"
                    placeholder={t('Password')}
                    passwordrules={passwordRules}
                />
                <InputError message={errors.password} />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{t('Confirm password')}</Label>
                <PasswordInput
                    id="password_confirmation"
                    required
                    autocomplete="new-password"
                    name="password_confirmation"
                    placeholder={t('Confirm password')}
                    passwordrules={passwordRules}
                />
                <InputError message={errors.password_confirmation} />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                disabled={processing}
                data-test="register-user-button"
            >
                {#if processing}<Spinner />{/if}
                {t('Create account')}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            {t('Already have an account?')}
            <TextLink href={login()} class="underline underline-offset-4">
                {t('Log in')}
            </TextLink>
        </div>
    {/snippet}
</Form>
