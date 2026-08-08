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
    import { t } from '@/lib/i18n';
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
        name: name || t('Project'),
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
            error = t('Please select a PNG, JPEG, GIF, or WebP image.');

            if (iconInput) {
                iconInput.value = '';
            }

            return;
        }

        if (file.size > maxIconBytes) {
            error = t('Project icon must be 1 MB or smaller.');

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
                      ? t('Failed to save project')
                      : t('Failed to create project');
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
                        {isEditing
                            ? t('Project settings')
                            : t('Create project')}
                    </Dialog.DialogTitle>
                    <Dialog.DialogDescription>
                        {t('Set the project name, dates, and icon.')}
                    </Dialog.DialogDescription>
                </div>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    aria-label={t('Close')}
                    onclick={close}
                >
                    <X />
                </Button>
            </div>

            <Field.FieldGroup>
                <Field.Field>
                    <Field.FieldLabel for="server-name">
                        {t('Project name')}
                    </Field.FieldLabel>
                    <Input
                        id="server-name"
                        bind:value={name}
                        maxlength={80}
                        placeholder={t('e.g. Project A')}
                        autofocus
                    />
                </Field.Field>

                <Field.FieldSet>
                    <Field.FieldLegend variant="label">
                        {t('Project icon')}
                    </Field.FieldLegend>
                    <Field.FieldDescription>
                        {t(
                            'When no icon is set, the first letter of the project name is shown.',
                        )}
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
                                {t('Click or')}<br />{t('Drop')}
                            </span>
                        </div>

                        <Field.FieldGroup class="min-w-0 flex-1">
                            <Field.Field>
                                <Field.FieldLabel for="server-icon">
                                    {t('Icon image')}
                                </Field.FieldLabel>
                                <Input
                                    id="server-icon"
                                    bind:ref={iconInput}
                                    type="file"
                                    accept="image/png,image/jpeg,image/gif,image/webp"
                                    onchange={selectIcon}
                                />
                                <Field.FieldDescription>
                                    {t(
                                        'PNG, JPEG, GIF, or WebP; 16-8192 px; max 1 MB. Images over 512 px are resized automatically.',
                                    )}
                                </Field.FieldDescription>
                                {#if iconPreviewUrl}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onclick={clearIcon}
                                    >
                                        {t('Remove icon')}
                                    </Button>
                                {/if}
                            </Field.Field>
                        </Field.FieldGroup>
                    </div>
                </Field.FieldSet>

                <Field.Field>
                    <Field.FieldLabel for="server-description">
                        {t('Description (optional)')}
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
                            {t('Start date')}
                        </Field.FieldLabel>
                        <Input
                            id="server-starts-on"
                            bind:value={startsOn}
                            type="date"
                        />
                    </Field.Field>
                    <Field.Field>
                        <Field.FieldLabel for="server-ends-on">
                            {t('End date')}
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
                    {t(
                        'Start and end dates are reflected in the calendar and Gantt chart.',
                    )}
                </Field.FieldDescription>

                {#if error}
                    <Field.Field data-invalid>
                        <Field.FieldError>{error}</Field.FieldError>
                    </Field.Field>
                {/if}
            </Field.FieldGroup>

            <Dialog.DialogFooter>
                <Button type="button" variant="outline" onclick={close}>
                    {t('Cancel')}
                </Button>
                <Button type="submit" disabled={saving || !name.trim()}>
                    {#if saving}
                        <Spinner data-icon="inline-start" />
                    {/if}
                    {isEditing ? t('Save') : t('Create')}
                </Button>
            </Dialog.DialogFooter>
        </form>
    </Dialog.DialogContent>
</Dialog.Dialog>
