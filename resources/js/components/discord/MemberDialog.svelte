<script lang="ts">
    import ArchiveIcon from 'lucide-svelte/icons/archive';
    import CircleAlert from 'lucide-svelte/icons/circle-alert';
    import CheckCircle2 from 'lucide-svelte/icons/circle-check-big';
    import RefreshCw from 'lucide-svelte/icons/refresh-cw';
    import RotateCcw from 'lucide-svelte/icons/rotate-ccw';
    import ShieldCheck from 'lucide-svelte/icons/shield-check';
    import ShieldOff from 'lucide-svelte/icons/shield-off';
    import Trash2 from 'lucide-svelte/icons/trash-2';
    import UserPlus from 'lucide-svelte/icons/user-plus';
    import X from 'lucide-svelte/icons/x';
    import ProjectIconDropTarget from '@/components/discord/ProjectIconDropTarget.svelte';
    import * as Alert from '@/components/ui/alert';
    import * as AlertDialog from '@/components/ui/alert-dialog';
    import * as Avatar from '@/components/ui/avatar';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import * as Dialog from '@/components/ui/dialog';
    import * as Field from '@/components/ui/field';
    import { Input } from '@/components/ui/input';
    import * as InputGroup from '@/components/ui/input-group';
    import { Separator } from '@/components/ui/separator';
    import { Spinner } from '@/components/ui/spinner';
    import { Textarea } from '@/components/ui/textarea';
    import { apiFetch, apiJson, HttpError } from '@/lib/http';
    import type {
        ProjectInvitationResource,
        ServerResource,
        UserResource,
    } from '@/types';

    let {
        server,
        members,
        invitations,
        canManage = false,
        onUpdated,
        onMembersUpdated,
        onArchived,
        onRestored,
        onDeleted,
        onClose,
    }: {
        server: ServerResource;
        members: UserResource[];
        invitations?: ProjectInvitationResource[];
        canManage?: boolean;
        onUpdated?: (server: ServerResource) => void;
        onMembersUpdated?: (members: UserResource[]) => void;
        onArchived?: (server: ServerResource) => void;
        onRestored?: (server: ServerResource) => void;
        onDeleted?: (serverId: number) => void;
        onClose: () => void;
    } = $props();

    let dialogOpen = $state(true);
    let email = $state('');
    let projectName = $state('');
    let projectDescription = $state('');
    let projectStartsOn = $state('');
    let projectEndsOn = $state('');
    let projectIconFile = $state<File | null>(null);
    let removeProjectIcon = $state(false);
    let projectIconPreviewUrl = $state<string | null>(null);
    let projectIconInput = $state<HTMLInputElement | null>(null);
    let localProjectIconPreviewUrl: string | null = null;
    let currentMembers = $state<UserResource[]>([]);
    let currentInvitations = $state<ProjectInvitationResource[]>([]);
    let initialized = false;
    let invitationsLoaded = false;
    let savingProject = $state(false);
    let addingMember = $state(false);
    let loadingInvitations = $state(false);
    let invitationActionId = $state<number | null>(null);
    let roleActionId = $state<number | null>(null);
    let removingMember = $state(false);
    let projectActionPending = $state(false);
    let error = $state('');
    let success = $state('');
    let memberToRemove = $state<UserResource | null>(null);
    let archiveConfirmationOpen = $state(false);
    let deleteConfirmationOpen = $state(false);

    const isArchived = $derived(Boolean(server.archived_at));
    const canEdit = $derived(canManage && !isArchived);
    const maxIconBytes = 1024 * 1024;
    const allowedIconTypes = new Set([
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
    ]);
    const previewServer = $derived({
        name: projectName || server.name,
        icon_url: projectIconPreviewUrl,
    });

    $effect.pre(() => {
        if (initialized) {
            return;
        }

        projectName = server.name;
        projectDescription = server.description ?? '';
        projectStartsOn = server.starts_on ?? '';
        projectEndsOn = server.ends_on ?? '';
        projectIconPreviewUrl = server.icon_url ?? null;
        currentMembers = [...members];
        currentInvitations = [...(invitations ?? [])];
        initialized = true;

        if (canManage && invitations === undefined) {
            void loadInvitations();
        }
    });

    function clearMessages() {
        error = '';
        success = '';
    }

    function isAdministrator(member: UserResource): boolean {
        return (
            member.id === server.created_by || member.pivot?.role === 'admin'
        );
    }

    function closeDialog() {
        clearLocalProjectIconPreview();
        dialogOpen = false;
        onClose();
    }

    function handleOpenChange(open: boolean) {
        dialogOpen = open;

        if (!open) {
            clearLocalProjectIconPreview();
            onClose();
        }
    }

    function clearLocalProjectIconPreview() {
        if (localProjectIconPreviewUrl) {
            URL.revokeObjectURL(localProjectIconPreviewUrl);
            localProjectIconPreviewUrl = null;
        }
    }

    function setProjectIconFile(file: File) {
        if (!allowedIconTypes.has(file.type)) {
            error = 'PNG、JPEG、GIF、WebP画像を選択してください';

            if (projectIconInput) {
                projectIconInput.value = '';
            }

            return;
        }

        if (file.size > maxIconBytes) {
            error = 'プロジェクトアイコンは1MB以下にしてください';

            if (projectIconInput) {
                projectIconInput.value = '';
            }

            return;
        }

        clearLocalProjectIconPreview();
        localProjectIconPreviewUrl = URL.createObjectURL(file);
        projectIconFile = file;
        projectIconPreviewUrl = localProjectIconPreviewUrl;
        removeProjectIcon = false;
        clearMessages();
    }

    function selectProjectIcon(event: Event) {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0] ?? null;

        if (file) {
            setProjectIconFile(file);
        }
    }

    function clearProjectIcon() {
        clearLocalProjectIconPreview();
        projectIconFile = null;
        projectIconPreviewUrl = null;
        removeProjectIcon = Boolean(server.icon_url);

        if (projectIconInput) {
            projectIconInput.value = '';
        }
    }

    async function saveProject() {
        if (!canEdit || savingProject || !projectName.trim()) {
            return;
        }

        savingProject = true;
        clearMessages();

        try {
            const form = new FormData();
            form.append('_method', 'PATCH');
            form.append('name', projectName.trim());
            form.append('description', projectDescription.trim() || '');
            form.append('starts_on', projectStartsOn || '');
            form.append('ends_on', projectEndsOn || '');

            if (projectIconFile) {
                form.append('icon', projectIconFile);
            }

            if (removeProjectIcon) {
                form.append('remove_icon', '1');
            }

            const data = await apiJson<{ server: ServerResource }>(
                `/servers/${server.id}`,
                {
                    method: 'POST',
                    body: form,
                },
            );

            clearLocalProjectIconPreview();
            projectIconFile = null;
            removeProjectIcon = false;
            projectIconPreviewUrl = data.server.icon_url ?? null;

            if (projectIconInput) {
                projectIconInput.value = '';
            }

            onUpdated?.(data.server);
            success = 'プロジェクト情報を保存しました';
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : 'プロジェクト情報の保存に失敗しました';
        } finally {
            savingProject = false;
        }
    }

    async function loadInvitations() {
        if (invitationsLoaded || loadingInvitations) {
            return;
        }

        invitationsLoaded = true;
        loadingInvitations = true;

        try {
            const data = await apiJson<{
                invitations: ProjectInvitationResource[];
            }>(`/servers/${server.id}/invitations`);
            currentInvitations = data.invitations;
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : '招待状況の取得に失敗しました';
        } finally {
            loadingInvitations = false;
        }
    }

    async function inviteMember() {
        if (!canEdit || addingMember || !email.trim()) {
            return;
        }

        addingMember = true;
        clearMessages();

        try {
            const data = await apiJson<{
                invitation: ProjectInvitationResource;
                delivery: 'email' | 'in_app';
            }>(`/servers/${server.id}/invitations`, {
                method: 'POST',
                body: JSON.stringify({ email: email.trim() }),
            });

            currentInvitations = [
                data.invitation,
                ...currentInvitations.filter(
                    (item) => item.id !== data.invitation.id,
                ),
            ];
            success =
                data.delivery === 'email'
                    ? 'アカウント作成ページへの案内メールを送信しました'
                    : '招待を送信しました。相手の回答待ちです';
            email = '';
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : '招待の送信に失敗しました';
        } finally {
            addingMember = false;
        }
    }

    async function resendInvitation(invitation: ProjectInvitationResource) {
        if (!canEdit || invitationActionId !== null) {
            return;
        }

        invitationActionId = invitation.id;
        clearMessages();

        try {
            const data = await apiJson<{
                invitation: ProjectInvitationResource;
            }>(`/servers/${server.id}/invitations/${invitation.id}/resend`, {
                method: 'POST',
            });
            currentInvitations = currentInvitations.map((item) =>
                item.id === data.invitation.id ? data.invitation : item,
            );
            success = `${invitation.email} に招待を再送しました`;
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : '招待の再送に失敗しました';
        } finally {
            invitationActionId = null;
        }
    }

    async function deleteInvitation(invitation: ProjectInvitationResource) {
        if (!canEdit || invitationActionId !== null) {
            return;
        }

        invitationActionId = invitation.id;
        clearMessages();

        try {
            await apiFetch(
                `/servers/${server.id}/invitations/${invitation.id}`,
                { method: 'DELETE' },
            );
            currentInvitations = currentInvitations.filter(
                (item) => item.id !== invitation.id,
            );
            success = `${invitation.email} への招待を削除しました`;
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : '招待の削除に失敗しました';
        } finally {
            invitationActionId = null;
        }
    }

    async function removeMember() {
        if (!canEdit || removingMember || memberToRemove === null) {
            return;
        }

        const member = memberToRemove;
        removingMember = true;
        clearMessages();

        try {
            await apiFetch(`/servers/${server.id}/members/${member.id}`, {
                method: 'DELETE',
            });
            currentMembers = currentMembers.filter(
                (item) => item.id !== member.id,
            );
            onMembersUpdated?.(currentMembers);
            success = `${member.name} を削除しました`;
            memberToRemove = null;
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : 'メンバーの削除に失敗しました';
        } finally {
            removingMember = false;
        }
    }

    async function updateMemberRole(
        member: UserResource,
        role: 'admin' | 'member',
    ) {
        if (!canEdit || roleActionId !== null) {
            return;
        }

        roleActionId = member.id;
        clearMessages();

        try {
            const data = await apiJson<{ user: UserResource }>(
                `/servers/${server.id}/members/${member.id}/role`,
                {
                    method: 'PATCH',
                    body: JSON.stringify({ role }),
                },
            );
            currentMembers = currentMembers.map((item) =>
                item.id === data.user.id ? data.user : item,
            );
            onMembersUpdated?.(currentMembers);
            success =
                role === 'admin'
                    ? `${member.name}を管理者に設定しました`
                    : `${member.name}を一般メンバーに変更しました`;
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : '管理者権限の変更に失敗しました';
        } finally {
            roleActionId = null;
        }
    }

    async function archiveProject() {
        if (!canManage || isArchived || projectActionPending) {
            return;
        }

        projectActionPending = true;
        clearMessages();

        try {
            const data = await apiJson<{ server: ServerResource }>(
                `/servers/${server.id}/archive`,
                { method: 'PATCH' },
            );
            onUpdated?.(data.server);

            if (onArchived) {
                onArchived(data.server);
                closeDialog();
            } else {
                window.location.href = '/servers';
            }
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : 'プロジェクトのアーカイブに失敗しました';
        } finally {
            projectActionPending = false;
        }
    }

    async function restoreProject() {
        if (!canManage || !isArchived || projectActionPending) {
            return;
        }

        projectActionPending = true;
        clearMessages();

        try {
            const data = await apiJson<{ server: ServerResource }>(
                `/servers/${server.id}/restore`,
                { method: 'PATCH' },
            );
            onUpdated?.(data.server);
            onRestored?.(data.server);
            closeDialog();
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : 'プロジェクトの復元に失敗しました';
        } finally {
            projectActionPending = false;
        }
    }

    async function deleteProject() {
        if (!canManage || projectActionPending) {
            return;
        }

        projectActionPending = true;
        clearMessages();

        try {
            await apiFetch(`/servers/${server.id}`, { method: 'DELETE' });

            if (onDeleted) {
                onDeleted(server.id);
                closeDialog();
            } else {
                window.location.href = '/servers';
            }
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : 'プロジェクトの削除に失敗しました';
        } finally {
            projectActionPending = false;
        }
    }
</script>

<Dialog.Dialog bind:open={dialogOpen} onOpenChange={handleOpenChange}>
    <Dialog.DialogContent
        class="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-2xl"
    >
        <div class="flex items-start justify-between gap-4">
            <div class="flex flex-col gap-1">
                <Dialog.DialogTitle>プロジェクト設定</Dialog.DialogTitle>
                <Dialog.DialogDescription>
                    プロジェクト情報と参加メンバーを管理します。
                </Dialog.DialogDescription>
            </div>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                aria-label="閉じる"
                onclick={closeDialog}
            >
                <X data-icon="inline-start" />
            </Button>
        </div>

        <div class="mt-6 flex flex-col gap-6">
            {#if isArchived}
                <Alert.Alert>
                    <ArchiveIcon />
                    <Alert.AlertDescription>
                        このプロジェクトはアーカイブ済みです。編集や投稿を再開するには復元してください。
                    </Alert.AlertDescription>
                </Alert.Alert>
            {/if}

            {#if error}
                <Alert.Alert variant="destructive">
                    <CircleAlert />
                    <Alert.AlertDescription>{error}</Alert.AlertDescription>
                </Alert.Alert>
            {:else if success}
                <Alert.Alert>
                    <CheckCircle2 />
                    <Alert.AlertDescription>{success}</Alert.AlertDescription>
                </Alert.Alert>
            {/if}

            <form
                class="flex flex-col gap-4"
                onsubmit={(event) => {
                    event.preventDefault();
                    saveProject();
                }}
            >
                <Field.FieldGroup class="gap-4">
                    <Field.FieldSet>
                        <Field.FieldLegend variant="label">
                            プロジェクトアイコン
                        </Field.FieldLegend>
                        <Field.FieldDescription>
                            未設定の場合はプロジェクト名の先頭文字を表示します。
                        </Field.FieldDescription>

                        <div class="flex items-start gap-4">
                            <div
                                class="flex shrink-0 flex-col items-center gap-1"
                            >
                                <ProjectIconDropTarget
                                    server={previewServer}
                                    disabled={!canEdit}
                                    onChoose={() => projectIconInput?.click()}
                                    onFile={setProjectIconFile}
                                />
                                {#if canEdit}
                                    <span
                                        class="text-center text-[10px] leading-tight text-muted-foreground"
                                    >
                                        クリックまたは<br />ドロップ
                                    </span>
                                {/if}
                            </div>

                            <Field.FieldGroup class="min-w-0 flex-1">
                                <Field.Field
                                    data-disabled={!canEdit || undefined}
                                >
                                    <Field.FieldLabel for="project-icon">
                                        アイコン画像
                                    </Field.FieldLabel>
                                    <Input
                                        id="project-icon"
                                        bind:ref={projectIconInput}
                                        type="file"
                                        accept="image/png,image/jpeg,image/gif,image/webp"
                                        disabled={!canEdit}
                                        onchange={selectProjectIcon}
                                    />
                                    <Field.FieldDescription>
                                        PNG・JPEG・GIF・WebP、16〜8192px、最大1MB。512px超は自動縮小
                                    </Field.FieldDescription>
                                    {#if canEdit && projectIconPreviewUrl}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onclick={clearProjectIcon}
                                        >
                                            アイコンを削除
                                        </Button>
                                    {/if}
                                </Field.Field>
                            </Field.FieldGroup>
                        </div>
                    </Field.FieldSet>

                    <Field.Field data-disabled={!canEdit || undefined}>
                        <Field.FieldLabel for="project-name">
                            プロジェクト名
                        </Field.FieldLabel>
                        <Input
                            id="project-name"
                            bind:value={projectName}
                            disabled={!canEdit}
                            maxlength={80}
                            required
                        />
                    </Field.Field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <Field.Field data-disabled={!canEdit || undefined}>
                            <Field.FieldLabel for="project-starts-on">
                                開始日
                            </Field.FieldLabel>
                            <Input
                                id="project-starts-on"
                                bind:value={projectStartsOn}
                                type="date"
                                disabled={!canEdit}
                            />
                        </Field.Field>
                        <Field.Field data-disabled={!canEdit || undefined}>
                            <Field.FieldLabel for="project-ends-on">
                                終了日
                            </Field.FieldLabel>
                            <Input
                                id="project-ends-on"
                                bind:value={projectEndsOn}
                                type="date"
                                min={projectStartsOn || undefined}
                                disabled={!canEdit}
                            />
                        </Field.Field>
                    </div>

                    <Field.Field data-disabled={!canEdit || undefined}>
                        <Field.FieldLabel for="project-description">
                            内容（任意）
                        </Field.FieldLabel>
                        <Textarea
                            id="project-description"
                            bind:value={projectDescription}
                            disabled={!canEdit}
                            maxlength={255}
                            rows={4}
                        />
                        <Field.FieldDescription>
                            開始日・終了日はカレンダーとガントチャートに反映されます。
                        </Field.FieldDescription>
                    </Field.Field>
                </Field.FieldGroup>

                {#if canEdit}
                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            disabled={savingProject || !projectName.trim()}
                        >
                            {#if savingProject}
                                <Spinner data-icon="inline-start" />
                            {/if}
                            保存
                        </Button>
                    </div>
                {/if}
            </form>

            <Separator />

            <Field.FieldSet>
                <Field.FieldLegend>メンバー</Field.FieldLegend>
                <Field.FieldDescription>
                    メールアドレスで招待します。未登録の場合はアカウント作成ページをメールで案内します。
                </Field.FieldDescription>

                {#if canEdit}
                    <Field.FieldGroup>
                        <Field.Field>
                            <Field.FieldLabel
                                for="member-email"
                                class="sr-only"
                            >
                                メンバーのメールアドレス
                            </Field.FieldLabel>
                            <InputGroup.Root>
                                <InputGroup.Input
                                    id="member-email"
                                    bind:value={email}
                                    type="email"
                                    placeholder="member@example.com"
                                    onkeydown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            inviteMember();
                                        }
                                    }}
                                />
                                <InputGroup.Addon align="inline-end">
                                    <Button
                                        type="button"
                                        size="sm"
                                        onclick={inviteMember}
                                        disabled={addingMember || !email.trim()}
                                    >
                                        {#if addingMember}
                                            <Spinner data-icon="inline-start" />
                                        {:else}
                                            <UserPlus
                                                data-icon="inline-start"
                                            />
                                        {/if}
                                        招待
                                    </Button>
                                </InputGroup.Addon>
                            </InputGroup.Root>
                        </Field.Field>
                    </Field.FieldGroup>
                {/if}

                {#if canManage && (loadingInvitations || currentInvitations.length > 0)}
                    <div class="flex flex-col gap-2">
                        <p class="text-sm font-medium">招待状況</p>
                        {#if loadingInvitations}
                            <div
                                class="flex items-center gap-2 px-2 py-3 text-sm text-muted-foreground"
                            >
                                <Spinner />
                                読み込み中
                            </div>
                        {:else}
                            {#each currentInvitations as invitation (invitation.id)}
                                <div
                                    class="flex flex-wrap items-center gap-3 rounded-md px-2 py-2 hover:bg-muted/60"
                                >
                                    <Avatar.Avatar class="size-9">
                                        <Avatar.AvatarFallback>
                                            {(
                                                invitation.user?.name ??
                                                invitation.email
                                            )
                                                .slice(0, 1)
                                                .toUpperCase()}
                                        </Avatar.AvatarFallback>
                                    </Avatar.Avatar>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <p
                                                class="truncate text-sm font-medium"
                                            >
                                                {invitation.user?.name ??
                                                    invitation.email}
                                            </p>
                                            {#if invitation.status === 'declined'}
                                                <Badge variant="destructive"
                                                    >拒否</Badge
                                                >
                                            {:else if invitation.registered}
                                                <Badge variant="outline"
                                                    >回答待ち</Badge
                                                >
                                            {:else}
                                                <Badge variant="secondary"
                                                    >登録待ち</Badge
                                                >
                                            {/if}
                                        </div>
                                        {#if invitation.user}
                                            <p
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {invitation.email}
                                            </p>
                                        {/if}
                                    </div>
                                    {#if canEdit}
                                        <div class="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                disabled={invitationActionId !==
                                                    null}
                                                onclick={() =>
                                                    resendInvitation(
                                                        invitation,
                                                    )}
                                            >
                                                {#if invitationActionId === invitation.id}
                                                    <Spinner
                                                        data-icon="inline-start"
                                                    />
                                                {:else}
                                                    <RefreshCw
                                                        data-icon="inline-start"
                                                    />
                                                {/if}
                                                再送
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                disabled={invitationActionId !==
                                                    null}
                                                onclick={() =>
                                                    deleteInvitation(
                                                        invitation,
                                                    )}
                                            >
                                                <Trash2
                                                    data-icon="inline-start"
                                                />
                                                招待を削除
                                            </Button>
                                        </div>
                                    {/if}
                                </div>
                            {/each}
                        {/if}
                    </div>
                {/if}

                <div class="flex max-h-64 flex-col gap-1 overflow-y-auto">
                    {#each currentMembers as member (member.id)}
                        <div
                            class="flex items-center gap-3 rounded-md px-2 py-2 hover:bg-muted/60"
                        >
                            <Avatar.Avatar class="size-9">
                                <Avatar.AvatarFallback>
                                    {member.name.slice(0, 1).toUpperCase()}
                                </Avatar.AvatarFallback>
                            </Avatar.Avatar>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-medium">
                                        {member.name}
                                    </p>
                                    {#if isAdministrator(member)}
                                        <Badge variant="secondary">管理者</Badge
                                        >
                                    {/if}
                                </div>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {member.email}
                                </p>
                            </div>
                            {#if canEdit && member.id !== server.created_by}
                                <div class="flex flex-wrap justify-end gap-1">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        disabled={roleActionId !== null}
                                        onclick={() =>
                                            updateMemberRole(
                                                member,
                                                isAdministrator(member)
                                                    ? 'member'
                                                    : 'admin',
                                            )}
                                    >
                                        {#if roleActionId === member.id}
                                            <Spinner data-icon="inline-start" />
                                        {:else if isAdministrator(member)}
                                            <ShieldOff
                                                data-icon="inline-start"
                                            />
                                        {:else}
                                            <ShieldCheck
                                                data-icon="inline-start"
                                            />
                                        {/if}
                                        {isAdministrator(member)
                                            ? '管理者から外す'
                                            : '管理者にする'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        aria-label={`${member.name}を削除`}
                                        disabled={roleActionId !== null}
                                        onclick={() =>
                                            (memberToRemove = member)}
                                    >
                                        <Trash2 data-icon="inline-start" />
                                    </Button>
                                </div>
                            {/if}
                        </div>
                    {/each}
                </div>
            </Field.FieldSet>

            {#if canManage}
                <Separator />

                <section
                    class="flex flex-col gap-3"
                    aria-labelledby="danger-zone"
                >
                    <div class="flex flex-col gap-1">
                        <h3 id="danger-zone" class="font-medium">管理操作</h3>
                        <p class="text-sm text-muted-foreground">
                            アーカイブは復元できます。削除するとプロジェクト内のデータはすべて失われます。
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        {#if isArchived}
                            <Button
                                type="button"
                                variant="outline"
                                onclick={restoreProject}
                                disabled={projectActionPending}
                            >
                                {#if projectActionPending}
                                    <Spinner data-icon="inline-start" />
                                {:else}
                                    <RotateCcw data-icon="inline-start" />
                                {/if}
                                復元
                            </Button>
                        {:else}
                            <Button
                                type="button"
                                variant="outline"
                                onclick={() => (archiveConfirmationOpen = true)}
                            >
                                <ArchiveIcon data-icon="inline-start" />
                                アーカイブ
                            </Button>
                        {/if}
                        <Button
                            type="button"
                            variant="destructive"
                            onclick={() => (deleteConfirmationOpen = true)}
                        >
                            <Trash2 data-icon="inline-start" />
                            完全に削除
                        </Button>
                    </div>
                </section>
            {/if}
        </div>

        <div class="mt-6">
            <Dialog.DialogFooter>
                <Button type="button" variant="outline" onclick={closeDialog}>
                    閉じる
                </Button>
            </Dialog.DialogFooter>
        </div>
    </Dialog.DialogContent>
</Dialog.Dialog>

<AlertDialog.Root bind:open={archiveConfirmationOpen}>
    <AlertDialog.Content>
        <AlertDialog.Header>
            <AlertDialog.Title>
                「{server.name}」をアーカイブしますか？
            </AlertDialog.Title>
            <AlertDialog.Description>
                サイドバーから非表示になり、編集や投稿が停止されます。プロジェクト一覧からいつでも復元できます。
            </AlertDialog.Description>
        </AlertDialog.Header>
        <AlertDialog.Footer>
            <AlertDialog.Cancel>キャンセル</AlertDialog.Cancel>
            <AlertDialog.Action
                onclick={archiveProject}
                disabled={projectActionPending}
            >
                アーカイブ
            </AlertDialog.Action>
        </AlertDialog.Footer>
    </AlertDialog.Content>
</AlertDialog.Root>

<AlertDialog.Root
    open={memberToRemove !== null}
    onOpenChange={(open) => {
        if (!open && !removingMember) {
            memberToRemove = null;
        }
    }}
>
    <AlertDialog.Content>
        <AlertDialog.Header>
            <AlertDialog.Title>
                {memberToRemove?.name ?? 'このメンバー'}を削除しますか？
            </AlertDialog.Title>
            <AlertDialog.Description>
                プロジェクトへアクセスできなくなります。担当中のタスクがある場合は削除できません。
            </AlertDialog.Description>
        </AlertDialog.Header>
        <AlertDialog.Footer>
            <AlertDialog.Cancel>キャンセル</AlertDialog.Cancel>
            <AlertDialog.Action
                variant="destructive"
                onclick={removeMember}
                disabled={removingMember}
            >
                削除
            </AlertDialog.Action>
        </AlertDialog.Footer>
    </AlertDialog.Content>
</AlertDialog.Root>

<AlertDialog.Root bind:open={deleteConfirmationOpen}>
    <AlertDialog.Content>
        <AlertDialog.Header>
            <AlertDialog.Title>
                「{server.name}」を完全に削除しますか？
            </AlertDialog.Title>
            <AlertDialog.Description>
                この操作は取り消せません。チャンネル、メッセージ、タスク、ファイルを含むすべてのデータが削除されます。
            </AlertDialog.Description>
        </AlertDialog.Header>
        <AlertDialog.Footer>
            <AlertDialog.Cancel>キャンセル</AlertDialog.Cancel>
            <AlertDialog.Action
                variant="destructive"
                onclick={deleteProject}
                disabled={projectActionPending}
            >
                完全に削除
            </AlertDialog.Action>
        </AlertDialog.Footer>
    </AlertDialog.Content>
</AlertDialog.Root>
