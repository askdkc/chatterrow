<script lang="ts">
    import X from 'lucide-svelte/icons/x';
    import ProjectIconDropTarget from '@/components/discord/ProjectIconDropTarget.svelte';
    import { Button } from '@/components/ui/button';
    import * as Dialog from '@/components/ui/dialog';
    import * as Field from '@/components/ui/field';
    import { Input } from '@/components/ui/input';
    import { Spinner } from '@/components/ui/spinner';
    import { Textarea } from '@/components/ui/textarea';
    import { apiJson, HttpError } from '@/lib/http';
    import type { ServerResource } from '@/types';

    let {
        server = null,
        onUpdated,
        onClose,
    }: {
        server?: ServerResource | null;
        onUpdated?: (server: ServerResource) => void;
        onClose: () => void;
    } = $props();

    let dialogOpen = $state(true);
    let name = $state('');
    let description = $state('');
    let startsOn = $state('');
    let endsOn = $state('');
    let iconFile = $state<File | null>(null);
    let removeIcon = $state(false);
    let iconPreviewUrl = $state<string | null>(null);
    let iconInput = $state<HTMLInputElement | null>(null);
    let localIconPreviewUrl: string | null = null;
    let saving = $state(false);
    let error = $state('');
    let initialized = false;

    const isEditing = $derived(server !== null);
    const maxIconBytes = 1024 * 1024;
    const allowedIconTypes = new Set([
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
    ]);
    const previewServer = $derived({
        name: name || 'プロジェクト',
        icon_url: iconPreviewUrl,
    });

    $effect.pre(() => {
        if (initialized) {
            return;
        }

        name = server?.name ?? '';
        description = server?.description ?? '';
        startsOn = server?.starts_on ?? '';
        endsOn = server?.ends_on ?? '';
        iconPreviewUrl = server?.icon_url ?? null;
        initialized = true;
    });

    function clearLocalIconPreview() {
        if (localIconPreviewUrl) {
            URL.revokeObjectURL(localIconPreviewUrl);
            localIconPreviewUrl = null;
        }
    }

    function close() {
        clearLocalIconPreview();
        dialogOpen = false;
        onClose();
    }

    function handleOpenChange(open: boolean) {
        dialogOpen = open;

        if (!open) {
            clearLocalIconPreview();
            onClose();
        }
    }

    function setIconFile(file: File) {
        if (!allowedIconTypes.has(file.type)) {
            error = 'PNG、JPEG、GIF、WebP画像を選択してください';

            if (iconInput) {
                iconInput.value = '';
            }

            return;
        }

        if (file.size > maxIconBytes) {
            error = 'プロジェクトアイコンは1MB以下にしてください';

            if (iconInput) {
                iconInput.value = '';
            }

            return;
        }

        clearLocalIconPreview();
        localIconPreviewUrl = URL.createObjectURL(file);
        iconFile = file;
        iconPreviewUrl = localIconPreviewUrl;
        removeIcon = false;
        error = '';
    }

    function selectIcon(event: Event) {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0] ?? null;

        if (file) {
            setIconFile(file);
        }
    }

    function clearIcon() {
        clearLocalIconPreview();
        iconFile = null;
        iconPreviewUrl = null;
        removeIcon = Boolean(server?.icon_url);

        if (iconInput) {
            iconInput.value = '';
        }
    }

    async function save(event?: SubmitEvent) {
        event?.preventDefault();

        if (saving || !name.trim()) {
            return;
        }

        saving = true;
        error = '';

        try {
            const form = new FormData();
            form.append('name', name.trim());
            form.append('description', description.trim() || '');
            form.append('starts_on', startsOn || '');
            form.append('ends_on', endsOn || '');

            if (iconFile) {
                form.append('icon', iconFile);
            }

            if (removeIcon) {
                form.append('remove_icon', '1');
            }

            if (server) {
                form.append('_method', 'PATCH');
            }

            const data = await apiJson<{ server: ServerResource }>(
                server ? `/servers/${server.id}` : '/servers',
                {
                    method: 'POST',
                    body: form,
                },
            );

            if (server) {
                onUpdated?.(data.server);
                close();
            } else {
                window.location.href = `/servers/${data.server.id}`;
            }
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : isEditing
                      ? '保存に失敗しました'
                      : '作成に失敗しました';
        } finally {
            saving = false;
        }
    }

    function handleDialogKeydown(event: KeyboardEvent) {
        if (event.key !== 'Enter') {
            return;
        }

        if (event.metaKey || event.ctrlKey) {
            event.preventDefault();
            void save();
        } else if (!(event.target instanceof HTMLTextAreaElement)) {
            event.preventDefault();
        }
    }
</script>

<svelte:window onkeydown={handleDialogKeydown} />

<Dialog.Dialog bind:open={dialogOpen} onOpenChange={handleOpenChange}>
    <Dialog.DialogContent
        class="max-h-[calc(100dvh-2rem)] overflow-y-auto sm:max-w-lg"
    >
        <form class="flex flex-col gap-6" novalidate onsubmit={save}>
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <Dialog.DialogTitle>
                        {isEditing ? 'プロジェクト設定' : 'プロジェクトを作成'}
                    </Dialog.DialogTitle>
                    <Dialog.DialogDescription>
                        名前、期間、アイコンを設定します。
                    </Dialog.DialogDescription>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label="閉じる"
                    onclick={close}
                >
                    <X />
                </Button>
            </div>

            <Field.FieldGroup>
                <Field.Field>
                    <Field.FieldLabel for="server-name">
                        プロジェクト名
                    </Field.FieldLabel>
                    <Input
                        id="server-name"
                        bind:value={name}
                        maxlength={80}
                        placeholder="例: プロジェクトA"
                        autofocus
                    />
                </Field.Field>

                <Field.FieldSet>
                    <Field.FieldLegend variant="label">
                        プロジェクトアイコン
                    </Field.FieldLegend>
                    <Field.FieldDescription>
                        未設定の場合はプロジェクト名の先頭文字を表示します。
                    </Field.FieldDescription>

                    <div class="flex items-start gap-4">
                        <div class="flex shrink-0 flex-col items-center gap-1">
                            <ProjectIconDropTarget
                                server={previewServer}
                                onChoose={() => iconInput?.click()}
                                onFile={setIconFile}
                            />
                            <span
                                class="text-center text-[10px] leading-tight text-muted-foreground"
                            >
                                クリックまたは<br />ドロップ
                            </span>
                        </div>

                        <Field.FieldGroup class="min-w-0 flex-1">
                            <Field.Field>
                                <Field.FieldLabel for="server-icon">
                                    アイコン画像
                                </Field.FieldLabel>
                                <Input
                                    id="server-icon"
                                    bind:ref={iconInput}
                                    type="file"
                                    accept="image/png,image/jpeg,image/gif,image/webp"
                                    onchange={selectIcon}
                                />
                                <Field.FieldDescription>
                                    PNG・JPEG・GIF・WebP、16〜8192px、最大1MB。512px超は自動縮小
                                </Field.FieldDescription>
                                {#if iconPreviewUrl}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onclick={clearIcon}
                                    >
                                        アイコンを削除
                                    </Button>
                                {/if}
                            </Field.Field>
                        </Field.FieldGroup>
                    </div>
                </Field.FieldSet>

                <Field.Field>
                    <Field.FieldLabel for="server-description">
                        内容（任意）
                    </Field.FieldLabel>
                    <Textarea
                        id="server-description"
                        bind:value={description}
                        maxlength={255}
                        rows={3}
                    />
                </Field.Field>

                <div class="grid gap-4 sm:grid-cols-2">
                    <Field.Field>
                        <Field.FieldLabel for="server-starts-on">
                            開始日
                        </Field.FieldLabel>
                        <Input
                            id="server-starts-on"
                            bind:value={startsOn}
                            type="date"
                        />
                    </Field.Field>
                    <Field.Field>
                        <Field.FieldLabel for="server-ends-on">
                            終了日
                        </Field.FieldLabel>
                        <Input
                            id="server-ends-on"
                            bind:value={endsOn}
                            type="date"
                            min={startsOn || undefined}
                        />
                    </Field.Field>
                </div>

                <Field.FieldDescription>
                    開始日・終了日はカレンダーとガントチャートに反映されます。
                </Field.FieldDescription>

                {#if error}
                    <Field.Field data-invalid>
                        <Field.FieldError>{error}</Field.FieldError>
                    </Field.Field>
                {/if}
            </Field.FieldGroup>

            <Dialog.DialogFooter>
                <Button type="button" variant="outline" onclick={close}>
                    キャンセル
                </Button>
                <Button type="submit" disabled={saving || !name.trim()}>
                    {#if saving}
                        <Spinner data-icon="inline-start" />
                    {/if}
                    {isEditing ? '保存' : '作成'}
                </Button>
            </Dialog.DialogFooter>
        </form>
    </Dialog.DialogContent>
</Dialog.Dialog>
