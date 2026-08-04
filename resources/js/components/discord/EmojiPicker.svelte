<script lang="ts">
    import { Ellipsis, Smile } from 'lucide-svelte';
    import { onMount } from 'svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import * as Popover from '@/components/ui/popover';
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
        onselect,
    }: {
        open?: boolean;
        align?: 'start' | 'center' | 'end';
        alignOffset?: number;
        onselect: (emoji: string) => void;
    } = $props();

    const RECENT_EMOJI_STORAGE_KEY = 'chatterrow.recent-emojis';
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
    let searchQuery = $state('');
    let activeCategoryId = $state(categories[0].id);
    let previewEmoji = $state('👋');
    let recentEmojis = $state([...DEFAULT_QUICK_EMOJIS]);

    const quickEmojis = $derived(
        [...recentEmojis, ...DEFAULT_QUICK_EMOJIS]
            .filter((emoji, index, all) => all.indexOf(emoji) === index)
            .slice(0, 8),
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
    });

    function resetPicker() {
        fullPickerOpen = false;
        searchQuery = '';
    }

    function handleOpenChange(nextOpen: boolean) {
        if (!nextOpen) {
            resetPicker();
        }
    }

    function expandPicker() {
        fullPickerOpen = true;
    }

    function chooseCategory(category: EmojiCategory) {
        activeCategoryId = category.id;
        previewEmoji = category.icon;
        searchQuery = '';
    }

    function selectEmoji(emoji: string) {
        recentEmojis = [
            emoji,
            ...recentEmojis.filter((item) => item !== emoji),
        ].slice(0, MAX_RECENT_EMOJIS);

        try {
            localStorage.setItem(
                RECENT_EMOJI_STORAGE_KEY,
                JSON.stringify(recentEmojis),
            );
        } catch {
            // Storage can be unavailable in private browsing; insertion still works.
        }

        onselect(emoji);
        resetPicker();
        open = false;
    }
</script>

<Popover.Root bind:open onOpenChange={handleOpenChange}>
    <Popover.Trigger
        type="button"
        class="rounded p-1.5 text-[#b5bac1] transition hover:bg-white/10 hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        aria-label="絵文字を選ぶ"
        title="絵文字を選ぶ"
    >
        <Smile class="h-4 w-4" />
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
                : 'w-max max-w-[calc(100vw-1rem)]',
        )}
    >
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
                                    aria-label={`${emoji}を挿入`}
                                    onclick={() => selectEmoji(emoji)}
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
                            aria-label={`最近使った${emoji}を挿入`}
                            onclick={() => selectEmoji(emoji)}
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
                <Button
                    variant="ghost"
                    size="icon"
                    class="size-8 shrink-0 rounded-full text-xl"
                    aria-label={`${emoji}を挿入`}
                    onclick={() => selectEmoji(emoji)}
                    onpointerenter={() => (previewEmoji = emoji)}
                >
                    <span aria-hidden="true">{emoji}</span>
                </Button>
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
