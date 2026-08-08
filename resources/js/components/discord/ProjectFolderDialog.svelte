<script lang="ts">
    import X from 'lucide-svelte/icons/x';
    import ProjectFolderIcon from '@/components/discord/ProjectFolderIcon.svelte';
    import { Button } from '@/components/ui/button';
    import * as Dialog from '@/components/ui/dialog';
    import * as Field from '@/components/ui/field';
    import { Input } from '@/components/ui/input';
    import { Spinner } from '@/components/ui/spinner';
    import { apiJson, HttpError } from '@/lib/http';
    import { t } from '@/lib/i18n';
    import type { ProjectFolderResource } from '@/types';

    let {
        folder = null,
        onSaved,
        onClose,
    }: {
        folder?: ProjectFolderResource | null;
        onSaved: (folder: ProjectFolderResource) => void;
        onClose: () => void;
    } = $props();

    let dialogOpen = $state(true);
    const maxIconBytes = 1024 * 1024;
    const allowedIconTypes = new Set([
        'image/png',
        'image/jpeg',
        'image/gif',
        'image/webp',
    ]);

    let name = $state('');
    let color = $state('#5865F2');
    let iconFile = $state<File | null>(null);
    let removeIcon = $state(false);
    let iconPreviewUrl = $state<string | null>(null);
    let localPreviewUrl: string | null = null;
    let iconInput = $state<HTMLInputElement | null>(null);
    let saving = $state(false);
    let error = $state('');
    let initialized = false;

    const isEditing = $derived(folder !== null);
    const colorIsValid = $derived(/^#[0-9a-f]{6}$/i.test(color));
    const previewFolder = $derived({
        name: name || t('Folder'),
        color,
        icon_url: iconPreviewUrl,
    });

    $effect.pre(() => {
        if (!initialized) {
            name = folder?.name ?? '';
            color = folder?.color ?? '#5865F2';
            iconPreviewUrl = folder?.icon_url ?? null;
            initialized = true;
        }
    });

    function handleOpenChange(open: boolean) {
        if (!open) {
            clearLocalPreview();
            onClose();
        }
    }

    function close() {
        clearLocalPreview();
        dialogOpen = false;
        onClose();
    }

    function clearLocalPreview() {
        if (localPreviewUrl) {
            URL.revokeObjectURL(localPreviewUrl);
            localPreviewUrl = null;
        }
    }

    function selectIcon(event: Event) {
        const input = event.currentTarget as HTMLInputElement;
        const file = input.files?.[0] ?? null;

        if (!file) {
            return;
        }

        if (!allowedIconTypes.has(file.type)) {
            error = t('Please select a PNG, JPEG, GIF, or WebP image.');
            input.value = '';

            return;
        }

        if (file.size > maxIconBytes) {
            error = t('Icon image must be 1 MB or smaller.');
            input.value = '';

            return;
        }

        clearLocalPreview();
        localPreviewUrl = URL.createObjectURL(file);
        iconFile = file;
        iconPreviewUrl = localPreviewUrl;
        removeIcon = false;
        error = '';
    }

    function clearIcon() {
        clearLocalPreview();
        iconFile = null;
        iconPreviewUrl = null;
        removeIcon = Boolean(folder?.icon_url);

        if (iconInput) {
            iconInput.value = '';
        }
    }

    async function save(event: SubmitEvent) {
        event.preventDefault();

        if (saving || !name.trim()) {
            return;
        }

        if (!colorIsValid) {
            error = t('Color must be in the #5865F2 format.');

            return;
        }

        saving = true;
        error = '';

        try {
            const form = new FormData();
            form.append('name', name.trim());
            form.append('color', color.toUpperCase());

            if (iconFile) {
                form.append('icon', iconFile);
            }

            if (removeIcon) {
                form.append('remove_icon', '1');
            }

            if (folder) {
                form.append('_method', 'PATCH');
            }

            const data = await apiJson<{ folder: ProjectFolderResource }>(
                folder ? `/project-folders/${folder.id}` : '/project-folders',
                {
                    method: 'POST',
                    body: form,
                },
            );

            onSaved(data.folder);
            close();
        } catch (exception) {
            error =
                exception instanceof HttpError
                    ? exception.messageText()
                    : isEditing
                      ? t('Failed to update folder')
                      : t('Failed to create folder');
        } finally {
            saving = false;
        }
    }
</script>

<Dialog.Dialog bind:open={dialogOpen} onOpenChange={handleOpenChange}>
    <Dialog.DialogContent class="sm:max-w-lg">
        <form class="flex flex-col gap-6" novalidate onsubmit={save}>
            <div class="flex items-start justify-between gap-4">
                <div class="flex flex-col gap-1">
                    <Dialog.DialogTitle>
                        {isEditing ? t('Edit folder') : t('Create folder')}
                    </Dialog.DialogTitle>
                    <Dialog.DialogDescription>
                        {t(
                            'Set a name, color, and icon to make projects easier to identify.',
                        )}
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
                    <Field.FieldLabel for="project-folder-name">
                        {t('Folder name')}
                    </Field.FieldLabel>
                    <Input
                        id="project-folder-name"
                        bind:value={name}
                        maxlength={80}
                        placeholder={t('e.g. Internal projects')}
                        autofocus
                    />
                </Field.Field>

                <Field.FieldSet>
                    <Field.FieldLegend variant="label">
                        {t('Folder appearance')}
                    </Field.FieldLegend>
                    <Field.FieldDescription>
                        {t(
                            'When no icon image is set, the folder icon uses the selected color.',
                        )}
                    </Field.FieldDescription>

                    <div class="flex items-start gap-4">
                        <ProjectFolderIcon
                            folder={previewFolder}
                            size="preview"
                        />

                        <Field.FieldGroup class="min-w-0 flex-1">
                            <Field.Field>
                                <Field.FieldLabel for="project-folder-color">
                                    {t('Color')}
                                </Field.FieldLabel>
                                <div class="flex items-center gap-2">
                                    <Input
                                        id="project-folder-color"
                                        type="color"
                                        class="w-16 shrink-0"
                                        bind:value={color}
                                    />
                                    <Input
                                        aria-label={t('Folder color hex code')}
                                        bind:value={color}
                                        maxlength={7}
                                        pattern="#[0-9A-Fa-f]{6}"
                                        placeholder="#5865F2"
                                    />
                                </div>
                            </Field.Field>

                            <Field.Field>
                                <Field.FieldLabel for="project-folder-icon">
                                    {t('Emoji or icon image')}
                                </Field.FieldLabel>
                                <Input
                                    id="project-folder-icon"
                                    bind:ref={iconInput}
                                    type="file"
                                    accept="image/png,image/jpeg,image/gif,image/webp"
                                    onchange={selectIcon}
                                />
                                <Field.FieldDescription>
                                    {t(
                                        'PNG, JPEG, GIF, or WebP; 16-512 px; max 1 MB',
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
                <Button
                    type="submit"
                    disabled={saving || !name.trim() || !colorIsValid}
                >
                    {#if saving}
                        <Spinner data-icon="inline-start" />
                    {/if}
                    {isEditing ? t('Save') : t('Create')}
                </Button>
            </Dialog.DialogFooter>
        </form>
    </Dialog.DialogContent>
</Dialog.Dialog>
