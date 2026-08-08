<script lang="ts">
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import Mail from 'lucide-svelte/icons/mail';
    import * as Alert from '@/components/ui/alert';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import {
        Card,
        CardAction,
        CardContent,
        CardDescription,
        CardFooter,
        CardHeader,
        CardTitle,
    } from '@/components/ui/card';
    import { Spinner } from '@/components/ui/spinner';
    import { apiJson, HttpError } from '@/lib/http';
    import { t } from '@/lib/i18n';
    import type { ProjectInvitationResource, ServerResource } from '@/types';

    let {
        invitation,
        onAccepted,
        onDeclined,
    }: {
        invitation: ProjectInvitationResource;
        onAccepted?: (server: ServerResource) => void;
        onDeclined?: (invitationId: number) => void;
    } = $props();

    let pendingAction = $state<'accept' | 'decline' | null>(null);
    let error = $state('');

    async function respond(action: 'accept' | 'decline') {
        if (pendingAction !== null) {
            return;
        }

        pendingAction = action;
        error = '';

        try {
            const data = await apiJson<{
                ok?: boolean;
                server?: ServerResource;
            }>(`/project-invitations/${invitation.id}/${action}`, {
                method: 'PATCH',
            });

            if (action === 'accept' && data.server) {
                onAccepted?.(data.server);
            } else {
                onDeclined?.(invitation.id);
            }
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : t('Failed to respond to invitation');
        } finally {
            pendingAction = null;
        }
    }
</script>

<Card>
    <CardHeader>
        <CardTitle>{invitation.server?.name ?? t('Project')}</CardTitle>
        <CardDescription>
            {t('You have been invited to join the project by :name.', {
                name: invitation.inviter?.name ?? t('Project administrator'),
            })}
        </CardDescription>
        <CardAction>
            <Badge variant="secondary">
                <Mail data-icon="inline-start" />
                {t('Invitation')}
            </Badge>
        </CardAction>
    </CardHeader>
    {#if invitation.server?.description || error}
        <CardContent class="flex flex-col gap-3">
            {#if invitation.server?.description}
                <p class="text-sm text-muted-foreground">
                    {invitation.server.description}
                </p>
            {/if}
            {#if error}
                <Alert.Alert variant="destructive">
                    <CircleAlert />
                    <Alert.AlertDescription>{error}</Alert.AlertDescription>
                </Alert.Alert>
            {/if}
        </CardContent>
    {/if}
    <CardFooter class="justify-end gap-2">
        <Button
            type="button"
            variant="outline"
            disabled={pendingAction !== null}
            onclick={() => respond('decline')}
        >
            {#if pendingAction === 'decline'}
                <Spinner data-icon="inline-start" />
            {/if}
            {t('Decline project invitation')}
        </Button>
        <Button
            type="button"
            disabled={pendingAction !== null}
            onclick={() => respond('accept')}
        >
            {#if pendingAction === 'accept'}
                <Spinner data-icon="inline-start" />
            {/if}
            {t('Accept project invitation')}
        </Button>
    </CardFooter>
</Card>
