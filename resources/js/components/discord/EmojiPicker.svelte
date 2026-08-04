<script lang="ts">
    import { Ellipsis, Smile, SmilePlus } from 'lucide-svelte';
    import { onMount, tick } from 'svelte';
    import StampReaction from '@/components/discord/StampReaction.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import * as Field from '@/components/ui/field';
    import { Input } from '@/components/ui/input';
    import * as InputGroup from '@/components/ui/input-group';
    import * as Popover from '@/components/ui/popover';
    import {
        createStampReaction,
        DEFAULT_STAMP_TEXTS,
        isStampReaction,
        MAX_STAMP_TEXT_LENGTH,
        normalizeStampReaction,
        stampReactionText,
    } from '@/lib/reactions';
    import { cn } from '@/lib/utils';

    type EmojiCategory = {
        id: string;
        label: string;
        icon: string;
        searchTerms: string;
        emojis: string[];
    };

    let {
        open = $bindable(false),
        align = 'center',
        alignOffset = 0,
        mode = 'insert',
        onselect,
    }: {
        open?: boolean;
        align?: 'start' | 'center' | 'end';
        alignOffset?: number;
        mode?: 'insert' | 'reaction';
        onselect: (emoji: string) => void;
    } = $props();

    const RECENT_EMOJI_STORAGE_KEY = 'chatterrow.recent-emojis';
    const REGISTERED_STAMP_STORAGE_KEY =
        'chatterrow.registered-stamp-reactions';
    const REGISTERED_STAMP_CHANGE_EVENT =
        'chatterrow:registered-stamp-reactions-change';
    const TEXT_STAMP_TRIGGER_EMOJI = '💬';
    const DEFAULT_QUICK_EMOJIS = [
        '💬',
        '👍',
        '👏',
        '👋',
        '❤️',
        '😂',
        '😊',
        '🎉',
    ];
    const MAX_RECENT_EMOJIS = 16;
    const MAX_REGISTERED_STAMPS = 100;
    const DEFAULT_STAMP_REACTIONS = DEFAULT_STAMP_TEXTS.map((text) =>
        createStampReaction(text),
    ).filter((value): value is string => value !== null);
    const triggerLabel = $derived(
        mode === 'reaction' ? 'リアクションを追加' : '絵文字を選ぶ',
    );
    const selectionAction = $derived(
        mode === 'reaction' ? 'リアクションに追加' : '挿入',
    );

    const categories: EmojiCategory[] = [
        {
            id: 'faces',
            label: '顔と感情',
            icon: '😀',
            searchTerms: '顔 感情 笑顔 smile face emotion',
            emojis: `😀 😃 😄 😁 😆 😅 🤣 😂 🙂 🙃 😉 😊 😇 🥰 😍 🤩 😘 😗 ☺️ 😚 😋 😛 😝 😜 🤪 🤨 🧐 🤓 😎 🥳 😏 😒 😞 😔 😟 😕 🙁 ☹️ 😣 😖 😫 😩 🥺 😢 😭 😤 😠 😡 🤬 🤯 😳 🥵 🥶 😱 😨 😰 😥 😓 🤗 🤔 🫣 🤭 🫢 🤫 🤥 😶 😐 😑 😬 🙄 😯 😦 😧 😮 😲 🥱 😴 🤤 😪`.split(
                ' ',
            ),
        },
        {
            id: 'gestures',
            label: '人とジェスチャー',
            icon: '👋',
            searchTerms: '人 手 ジェスチャー person hand gesture',
            emojis: `👋 🤚 🖐️ ✋ 🖖 🫱 🫲 🫳 🫴 👌 🤌 🤏 ✌️ 🤞 🫰 🤟 🤘 🤙 👈 👉 👆 👇 ☝️ 👍 👎 ✊ 👊 🤛 🤜 👏 🙌 🫶 👐 🤲 🤝 🙏 ✍️ 💅 🤳 💪 🦾 🦵 🦶 👂 👃 🧠 🫀 🫁 🦷 👀 👁️ 👅 👄`.split(
                ' ',
            ),
        },
        {
            id: 'animals',
            label: '動物と自然',
            icon: '🐱',
            searchTerms: '動物 自然 animal nature pet',
            emojis: `🐶 🐱 🐭 🐹 🐰 🦊 🐻 🐼 🐻‍❄️ 🐨 🐯 🦁 🐮 🐷 🐸 🐵 🙈 🙉 🙊 🐔 🐧 🐦 🐤 🦆 🦅 🦉 🦇 🐺 🐗 🐴 🦄 🐝 🪲 🐞 🦋 🐌 🐢 🐍 🦎 🐙 🦑 🦀 🐠 🐟 🐬 🐳 🌸 🌻 🌞 ⭐ 🌈 🔥`.split(
                ' ',
            ),
        },
        {
            id: 'food',
            label: '食べ物と飲み物',
            icon: '🍎',
            searchTerms: '食べ物 飲み物 food drink fruit',
            emojis: `🍏 🍎 🍐 🍊 🍋 🍌 🍉 🍇 🍓 🫐 🍈 🍒 🍑 🥭 🍍 🥝 🍅 🥑 🥦 🥬 🥒 🌶️ 🫑 🌽 🥕 🧄 🧅 🍞 🥐 🥖 🥨 🧀 🥚 🍳 🥞 🧇 🍔 🍟 🍕 🌭 🥪 🌮 🍣 🍜 🍙 🍚 🍛 🍦 🍩 🍪 🎂 🍰 ☕ 🍵 🥤 🍺 🍷`.split(
                ' ',
            ),
        },
        {
            id: 'activities',
            label: 'アクティビティ',
            icon: '⚽',
            searchTerms: '活動 スポーツ 遊び activity sports game',
            emojis: `⚽ 🏀 🏈 ⚾ 🥎 🎾 🏐 🏉 🥏 🎱 🪀 🏓 🏸 🏒 🏑 🥍 🏏 🪃 🥅 ⛳ 🪁 🏹 🎣 🤿 🥊 🥋 🎽 🛹 🛼 🛷 ⛸️ 🥌 🎿 🏂 🪂 🏋️ 🤸 ⛹️ 🤺 🤾 🏌️ 🏇 🧘 🏄 🏊 🚴 🏆 🥇 🎮 🧩 🎨 🎭 🎤 🎸 🎹`.split(
                ' ',
            ),
        },
        {
            id: 'travel',
            label: '旅行と場所',
            icon: '🏠',
            searchTerms: '旅行 場所 乗り物 travel place vehicle home',
            emojis: `🚗 🚕 🚌 🚎 🏎️ 🚓 🚑 🚒 🚐 🛻 🚚 🚲 🛵 🏍️ 🚂 🚆 🚇 🚄 ✈️ 🚀 🚁 ⛵ 🚤 🛳️ 🗺️ 🗿 🗽 🗼 🏰 🏯 🏟️ 🎡 🎢 🏖️ 🏝️ ⛰️ 🏕️ 🏠 🏡 🏢 🏥 🏫 🏪 ⛩️ 🕌 ⛪ 🌇 🌃 🌉 🌌`.split(
                ' ',
            ),
        },
        {
            id: 'objects',
            label: 'もの',
            icon: '📝',
            searchTerms: 'もの 道具 object tool memo note',
            emojis: `⌚ 📱 💻 ⌨️ 🖥️ 🖨️ 🖱️ 📷 📹 🎥 📞 📺 📻 ⏰ ⌛ 🔋 💡 🔦 🕯️ 🧯 💸 💰 💎 ⚖️ 🔧 🔨 🛠️ ⛏️ 🔩 ⚙️ 🧰 🔗 🧲 🪜 🧪 🔬 🔭 📡 💊 🩹 🚪 🪑 🚽 🚿 🛁 🔑 🎁 🎈 ✉️ 📦 📅 📌 📎 ✂️ 📝 ✏️ 🔍 🔒`.split(
                ' ',
            ),
        },
        {
            id: 'symbols',
            label: '記号',
            icon: '⛔',
            searchTerms: '記号 マーク symbol sign heart',
            emojis: `❤️ 🧡 💛 💚 💙 💜 🖤 🤍 🤎 💔 ❣️ 💕 💞 💓 💗 💖 💘 💝 💟 ☮️ ✝️ ☪️ 🕉️ ☸️ ✡️ 🔯 ♈ ♉ ♊ ♋ ♌ ♍ ♎ ♏ ♐ ♑ ♒ ♓ ⛎ ♀️ ♂️ ⚧️ ▶️ ⏸️ ⏹️ ⏺️ ⏭️ ⏮️ ⏩ ⏪ 🔀 🔁 ✅ ❌ ❗ ❓ 💯 🚫 ⛔ ⚠️ ♻️ ➕ ➖ ➗`.split(
                ' ',
            ),
        },
        {
            id: 'flags',
            label: '旗',
            icon: '🏁',
            searchTerms: '旗 国 flag country japan',
            emojis: `🏁 🚩 🎌 🏴 🏳️ 🏳️‍🌈 🏳️‍⚧️ 🇯🇵 🇺🇸 🇨🇦 🇲🇽 🇧🇷 🇦🇷 🇬🇧 🇫🇷 🇩🇪 🇮🇹 🇪🇸 🇵🇹 🇳🇱 🇧🇪 🇨🇭 🇦🇹 🇸🇪 🇳🇴 🇩🇰 🇫🇮 🇵🇱 🇺🇦 🇹🇷 🇮🇳 🇨🇳 🇰🇷 🇹🇭 🇻🇳 🇸🇬 🇮🇩 🇵🇭 🇦🇺 🇳🇿 🇿🇦`.split(
                ' ',
            ),
        },
    ];

    const emojiKeywords: Record<string, string> = {
        '😀': '笑顔 にこにこ smile grin',
        '😂': '笑い 泣き笑い laugh joy',
        '😊': '笑顔 にこにこ smile blush',
        '😍': 'ハート 目 love heart eyes',
        '❤️': '赤い ハート love heart red',
        '👍': 'いいね 親指 thumbs up like',
        '👎': 'よくない 親指 thumbs down dislike',
        '👋': '手を振る wave hello',
        '👏': '拍手 clap applause',
        '🙏': 'お願い 祈る thanks pray',
        '🎉': 'お祝い パーティー celebration party',
        '🐱': '猫 cat',
        '🐶': '犬 dog',
        '🍎': 'りんご apple',
        '🍕': 'ピザ pizza',
        '⚽': 'サッカー soccer football',
        '🏠': '家 home house',
        '📝': 'メモ 鉛筆 note memo pencil',
        '⛔': '禁止 stop no entry',
        '🇯🇵': '日本 japan flag',
    };

    let fullPickerOpen = $state(false);
    let stampPickerOpen = $state(false);
    let stampText = $state('');
    let stampTextColor = $state('#111827');
    let stampBackgroundColor = $state('#ffffff');
    let stampBackgroundTransparent = $state(false);
    let stampInput: HTMLInputElement | null = $state(null);
    let searchQuery = $state('');
    let activeCategoryId = $state(categories[0].id);
    let previewEmoji = $state('👋');
    let recentEmojis = $state([...DEFAULT_QUICK_EMOJIS]);
    let registeredStampReactions = $state<string[]>([]);

    const quickEmojis = $derived(
        [
            ...(mode === 'reaction' ? [TEXT_STAMP_TRIGGER_EMOJI] : []),
            ...recentEmojis,
            ...DEFAULT_QUICK_EMOJIS,
        ]
            .filter((emoji, index, all) => all.indexOf(emoji) === index)
            .slice(0, 8),
    );
    const customStampReaction = $derived(
        createStampReaction(stampText, {
            textColor: stampTextColor,
            backgroundColor: stampBackgroundTransparent
                ? null
                : stampBackgroundColor,
        }),
    );
    const availableStampReactions = $derived(
        [...registeredStampReactions, ...DEFAULT_STAMP_REACTIONS].filter(
            (reaction, index, all) => all.indexOf(reaction) === index,
        ),
    );
    const visibleEmojis = $derived.by(() => {
        const query = searchQuery.trim().toLocaleLowerCase();

        if (!query) {
            return (
                categories.find(({ id }) => id === activeCategoryId)?.emojis ??
                []
            );
        }

        return categories.flatMap((category) => {
            const categoryMatches =
                category.label.toLocaleLowerCase().includes(query) ||
                category.searchTerms.toLocaleLowerCase().includes(query);

            return category.emojis.filter(
                (emoji) =>
                    categoryMatches ||
                    emoji.includes(query) ||
                    (emojiKeywords[emoji] ?? '')
                        .toLocaleLowerCase()
                        .includes(query),
            );
        });
    });

    function normalizeRegisteredStampReactions(values: unknown): string[] {
        if (!Array.isArray(values)) {
            return [];
        }

        return values
            .map((value) =>
                typeof value === 'string'
                    ? normalizeStampReaction(value)
                    : null,
            )
            .filter((value): value is string => value !== null)
            .filter((reaction, index, all) => all.indexOf(reaction) === index)
            .slice(0, MAX_REGISTERED_STAMPS);
    }

    function readRegisteredStampReactions(): string[] {
        try {
            return normalizeRegisteredStampReactions(
                JSON.parse(
                    localStorage.getItem(REGISTERED_STAMP_STORAGE_KEY) ?? '[]',
                ),
            );
        } catch {
            return [];
        }
    }

    onMount(() => {
        try {
            const stored = JSON.parse(
                localStorage.getItem(RECENT_EMOJI_STORAGE_KEY) ?? '[]',
            );

            if (Array.isArray(stored)) {
                const validEmojis = stored.filter(
                    (emoji): emoji is string =>
                        typeof emoji === 'string' && emoji.length > 0,
                );

                if (validEmojis.length > 0) {
                    recentEmojis = validEmojis.slice(0, MAX_RECENT_EMOJIS);
                }
            }
        } catch {
            // Invalid local data should not prevent the picker from opening.
        }

        registeredStampReactions = readRegisteredStampReactions();

        const syncRegisteredStamps = (event: Event) => {
            if (event instanceof CustomEvent) {
                registeredStampReactions = normalizeRegisteredStampReactions(
                    event.detail,
                );
            }
        };
        const syncRegisteredStampsFromStorage = (event: StorageEvent) => {
            if (event.key === REGISTERED_STAMP_STORAGE_KEY) {
                registeredStampReactions = readRegisteredStampReactions();
            }
        };

        window.addEventListener(
            REGISTERED_STAMP_CHANGE_EVENT,
            syncRegisteredStamps,
        );
        window.addEventListener('storage', syncRegisteredStampsFromStorage);

        return () => {
            window.removeEventListener(
                REGISTERED_STAMP_CHANGE_EVENT,
                syncRegisteredStamps,
            );
            window.removeEventListener(
                'storage',
                syncRegisteredStampsFromStorage,
            );
        };
    });

    function resetPicker() {
        fullPickerOpen = false;
        stampPickerOpen = false;
        stampText = '';
        searchQuery = '';
    }

    function handleOpenChange(nextOpen: boolean) {
        if (!nextOpen) {
            resetPicker();
        }
    }

    function expandPicker() {
        stampPickerOpen = false;
        fullPickerOpen = true;
    }

    async function openStampPicker() {
        fullPickerOpen = false;
        stampPickerOpen = true;
        await tick();
        stampInput?.focus();
    }

    function chooseCategory(category: EmojiCategory) {
        activeCategoryId = category.id;
        previewEmoji = category.icon;
        searchQuery = '';
    }

    function registerStampReaction(value: string) {
        const normalizedValue = normalizeStampReaction(value);

        if (!normalizedValue) {
            return;
        }

        registeredStampReactions = [
            normalizedValue,
            ...registeredStampReactions.filter(
                (reaction) => reaction !== normalizedValue,
            ),
        ].slice(0, MAX_REGISTERED_STAMPS);

        try {
            localStorage.setItem(
                REGISTERED_STAMP_STORAGE_KEY,
                JSON.stringify(registeredStampReactions),
            );
            window.dispatchEvent(
                new CustomEvent(REGISTERED_STAMP_CHANGE_EVENT, {
                    detail: registeredStampReactions,
                }),
            );
        } catch {
            // Storage can be unavailable; the stamp can still be used now.
        }
    }

    function selectValue(value: string) {
        if (isStampReaction(value)) {
            registerStampReaction(value);
        } else {
            recentEmojis = [
                value,
                ...recentEmojis.filter((item) => item !== value),
            ].slice(0, MAX_RECENT_EMOJIS);

            try {
                localStorage.setItem(
                    RECENT_EMOJI_STORAGE_KEY,
                    JSON.stringify(recentEmojis),
                );
            } catch {
                // Storage can be unavailable in private browsing; insertion still works.
            }
        }

        onselect(value);
        resetPicker();
        open = false;
    }

    function submitStamp(event: SubmitEvent) {
        event.preventDefault();

        if (customStampReaction) {
            selectValue(customStampReaction);
        }
    }
</script>

<Popover.Root bind:open onOpenChange={handleOpenChange}>
    <Popover.Trigger
        type="button"
        class={cn(
            'transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            mode === 'reaction'
                ? 'flex size-7 items-center justify-center rounded-full border bg-secondary text-muted-foreground hover:bg-accent hover:text-foreground'
                : 'rounded p-1.5 text-[#b5bac1] hover:bg-white/10 hover:text-white',
        )}
        aria-label={triggerLabel}
        title={triggerLabel}
    >
        {#if mode === 'reaction'}
            <SmilePlus class="size-5" />
        {:else}
            <Smile class="size-4" />
        {/if}
    </Popover.Trigger>
    <Popover.Content
        side="top"
        {align}
        {alignOffset}
        sideOffset={8}
        onOpenAutoFocus={(event) => event.preventDefault()}
        class={cn(
            'gap-2.5 bg-transparent p-0 shadow-none ring-0',
            fullPickerOpen
                ? 'w-[min(34rem,calc(100vw-1rem))]'
                : stampPickerOpen
                  ? 'w-[min(20rem,calc(100vw-1rem))]'
                  : 'w-max max-w-[calc(100vw-1rem)]',
        )}
    >
        {#if stampPickerOpen && mode === 'reaction'}
            <section
                class="animate-in fade-in-0 slide-in-from-bottom-2 flex flex-col gap-2 rounded-2xl border bg-popover p-2 text-popover-foreground shadow-xl duration-150"
                aria-label="文字ハンコ"
            >
                <form onsubmit={submitStamp}>
                    <Field.FieldGroup class="gap-2">
                        <Field.Field>
                            <Field.FieldLabel
                                for="stamp-reaction-text"
                                class="sr-only"
                            >
                                ハンコにする文字
                            </Field.FieldLabel>
                            <InputGroup.Root>
                                <InputGroup.Input
                                    bind:ref={stampInput}
                                    id="stamp-reaction-text"
                                    bind:value={stampText}
                                    maxlength={MAX_STAMP_TEXT_LENGTH}
                                    autocomplete="off"
                                    placeholder="文字を入力（4文字まで）"
                                />
                                <InputGroup.Addon align="inline-end">
                                    {#if customStampReaction}
                                        <StampReaction
                                            value={customStampReaction}
                                            size="chip"
                                        />
                                    {/if}
                                    <InputGroup.Button
                                        type="submit"
                                        disabled={!customStampReaction}
                                    >
                                        追加
                                    </InputGroup.Button>
                                </InputGroup.Addon>
                            </InputGroup.Root>
                        </Field.Field>

                        <Field.FieldSet>
                            <Field.FieldLegend class="sr-only">
                                文字スタンプの色
                            </Field.FieldLegend>
                            <div class="grid grid-cols-2 gap-2">
                                <Field.Field orientation="horizontal">
                                    <Field.FieldLabel for="stamp-text-color">
                                        文字色
                                    </Field.FieldLabel>
                                    <Input
                                        id="stamp-text-color"
                                        type="color"
                                        class="size-8 shrink-0 p-1"
                                        bind:value={stampTextColor}
                                    />
                                </Field.Field>
                                <Field.Field
                                    orientation="horizontal"
                                    data-disabled={stampBackgroundTransparent}
                                >
                                    <Field.FieldLabel
                                        for="stamp-background-color"
                                    >
                                        背景色
                                    </Field.FieldLabel>
                                    <Input
                                        id="stamp-background-color"
                                        type="color"
                                        class="size-8 shrink-0 p-1"
                                        bind:value={stampBackgroundColor}
                                        disabled={stampBackgroundTransparent}
                                    />
                                </Field.Field>
                            </div>
                            <Field.Field orientation="horizontal">
                                <Checkbox
                                    id="stamp-background-transparent"
                                    bind:checked={stampBackgroundTransparent}
                                />
                                <Field.FieldLabel
                                    for="stamp-background-transparent"
                                >
                                    背景なし
                                </Field.FieldLabel>
                            </Field.Field>
                        </Field.FieldSet>
                    </Field.FieldGroup>
                </form>

                <div
                    class="grid max-h-48 grid-cols-4 gap-1 overflow-y-auto"
                    aria-label="登録済みの文字スタンプ"
                >
                    {#each availableStampReactions as stampReaction (stampReaction)}
                        {@const text = stampReactionText(stampReaction)}
                        {#if text}
                            <Button
                                variant="ghost"
                                size="icon"
                                class="size-14 rounded-xl"
                                aria-label={`ハンコ「${text}」をリアクションに追加`}
                                onclick={() => selectValue(stampReaction)}
                            >
                                <StampReaction value={stampReaction} />
                            </Button>
                        {/if}
                    {/each}
                </div>
            </section>
        {/if}

        {#if fullPickerOpen}
            <section
                class="animate-in fade-in-0 slide-in-from-bottom-2 overflow-hidden rounded-3xl border bg-popover text-popover-foreground shadow-xl duration-150"
                aria-label="絵文字一覧"
            >
                <div class="flex items-center gap-3 p-3">
                    <Input
                        type="search"
                        bind:value={searchQuery}
                        aria-label="絵文字を検索"
                        placeholder="絵文字を検索"
                        class="h-11 rounded-2xl"
                    />
                    <span class="shrink-0 text-3xl" aria-hidden="true"
                        >{previewEmoji}</span
                    >
                </div>

                <nav
                    class="flex items-center gap-1 overflow-x-auto border-b px-2"
                    aria-label="絵文字カテゴリー"
                >
                    {#each categories as category (category.id)}
                        <Button
                            variant="ghost"
                            size="icon"
                            class={cn(
                                'relative shrink-0 rounded-none text-xl after:absolute after:inset-x-0 after:bottom-0 after:h-0.5',
                                activeCategoryId === category.id &&
                                    !searchQuery &&
                                    'after:bg-primary',
                            )}
                            aria-label={`${category.label}カテゴリー`}
                            aria-pressed={activeCategoryId === category.id &&
                                !searchQuery}
                            onclick={() => chooseCategory(category)}
                            onpointerenter={() =>
                                (previewEmoji = category.icon)}
                        >
                            <span aria-hidden="true">{category.icon}</span>
                        </Button>
                    {/each}
                </nav>

                <div
                    class="h-64 overflow-y-auto overscroll-contain p-2"
                    aria-live="polite"
                >
                    {#if visibleEmojis.length > 0}
                        <div
                            class="grid grid-cols-8 gap-1"
                            aria-label="選択可能な絵文字"
                        >
                            {#each visibleEmojis as emoji (emoji)}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="rounded-xl text-2xl"
                                    aria-label={`${emoji}を${selectionAction}`}
                                    onclick={() => selectValue(emoji)}
                                    onpointerenter={() =>
                                        (previewEmoji = emoji)}
                                >
                                    <span aria-hidden="true">{emoji}</span>
                                </Button>
                            {/each}
                        </div>
                    {:else}
                        <div
                            class="flex h-full items-center justify-center text-sm text-muted-foreground"
                        >
                            一致する絵文字はありません
                        </div>
                    {/if}
                </div>

                <div
                    class="flex items-center gap-1 overflow-x-auto border-t p-2"
                    aria-label="最近使った絵文字"
                >
                    {#each recentEmojis.slice(0, 10) as emoji (emoji)}
                        <Button
                            variant="ghost"
                            size="icon"
                            class="shrink-0 rounded-xl text-2xl"
                            aria-label={`最近使った${emoji}を${selectionAction}`}
                            onclick={() => selectValue(emoji)}
                            onpointerenter={() => (previewEmoji = emoji)}
                        >
                            <span aria-hidden="true">{emoji}</span>
                        </Button>
                    {/each}
                </div>
            </section>
        {/if}

        <div
            class="flex items-center gap-0.5 overflow-x-auto rounded-full border bg-popover p-1 text-popover-foreground shadow-xl"
            aria-label="クイック絵文字"
        >
            {#each quickEmojis as emoji (emoji)}
                {#if mode === 'reaction' && emoji === TEXT_STAMP_TRIGGER_EMOJI}
                    <Button
                        variant="ghost"
                        size="icon"
                        class={cn(
                            'size-8 shrink-0 rounded-full text-[22px]',
                            stampPickerOpen &&
                                'ring-2 ring-primary ring-offset-2',
                        )}
                        aria-label="文字ハンコを作る"
                        aria-expanded={stampPickerOpen}
                        onclick={openStampPicker}
                    >
                        <span aria-hidden="true">{emoji}</span>
                    </Button>
                {:else}
                    <Button
                        variant="ghost"
                        size="icon"
                        class="size-8 shrink-0 rounded-full text-[22px]"
                        aria-label={`${emoji}を${selectionAction}`}
                        onclick={() => selectValue(emoji)}
                        onpointerenter={() => (previewEmoji = emoji)}
                    >
                        <span aria-hidden="true">{emoji}</span>
                    </Button>
                {/if}
            {/each}
            <Button
                variant="ghost"
                size="icon"
                class={cn(
                    'ml-auto size-8 shrink-0 rounded-full',
                    fullPickerOpen && 'ring-2 ring-primary ring-offset-2',
                )}
                aria-label="すべての絵文字を表示"
                aria-expanded={fullPickerOpen}
                onclick={expandPicker}
            >
                <Ellipsis />
            </Button>
        </div>
    </Popover.Content>
</Popover.Root>
