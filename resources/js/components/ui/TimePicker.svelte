<script lang="ts">
    let {
        value = $bindable(''),
        id,
        placeholder = '時刻を選択',
        disabled = false,
        class: className = '',
        onValueChange,
    }: {
        value?: string;
        id?: string;
        placeholder?: string;
        disabled?: boolean;
        class?: string;
        onValueChange?: (value: string) => void;
    } = $props();

    let root = $state<HTMLDivElement>();
    let optionsList = $state<HTMLDivElement>();
    let open = $state(false);

    const options = Array.from({ length: 48 }, (_, index) => {
        const hours = Math.floor(index / 2);
        const minutes = index % 2 === 0 ? '00' : '30';

        return `${String(hours).padStart(2, '0')}:${minutes}`;
    });
    const optionsId = $derived(id ? `${id}-options` : undefined);

    $effect(() => {
        if (!open) {
            return;
        }

        const selected = optionsList?.querySelector<HTMLElement>(
            '[aria-selected="true"]',
        );
        selected?.scrollIntoView?.({ block: 'nearest' });
    });

    function normalizeTime(input: string): string | null {
        const match = input.trim().match(/^(\d{1,2}):([0-5]\d)$/);

        if (!match) {
            return null;
        }

        const hours = Number(match[1]);
        const minutes = Number(match[2]);

        if (hours > 23 || ![0, 30].includes(minutes)) {
            return null;
        }

        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
    }

    function updateValue(nextValue: string) {
        value = nextValue;
        onValueChange?.(nextValue);
    }

    function handleInput(event: Event) {
        updateValue((event.currentTarget as HTMLInputElement).value);
        open = true;
    }

    function selectOption(option: string) {
        updateValue(option);
        open = false;
    }

    function handleInputKeydown(event: KeyboardEvent) {
        if (event.key === 'Escape') {
            event.preventDefault();
            open = false;
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            open = true;
        } else if (event.key === 'Enter' && open) {
            const normalized = normalizeTime(value);

            if (normalized) {
                event.preventDefault();
                selectOption(normalized);
            }
        }
    }

    function handleInputBlur() {
        if (!value) {
            return;
        }

        updateValue(normalizeTime(value) ?? '');
    }

    function handleWindowPointerDown(event: PointerEvent) {
        if (open && root && !root.contains(event.target as Node)) {
            open = false;
        }
    }
</script>

<svelte:window onpointerdown={handleWindowPointerDown} />

<div bind:this={root} class={`relative min-w-0 ${className}`}>
        <input
        {id}
        value={value}
        type="text"
        inputmode="numeric"
        autocomplete="off"
        placeholder={placeholder}
        {disabled}
        role="combobox"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-autocomplete="list"
        aria-controls={optionsId}
        aria-invalid={value !== '' && !options.includes(value)
            ? 'true'
            : undefined}
        onfocus={() => !disabled && (open = true)}
        onclick={() => !disabled && (open = true)}
        oninput={handleInput}
        onblur={handleInputBlur}
        onkeydown={handleInputKeydown}
        class="w-full rounded-md bg-[#383a40] px-3 py-2 text-sm text-[#dbdee1] outline-none placeholder:text-[#6d6f78] focus:ring-1 focus:ring-[#5865f2] disabled:cursor-not-allowed disabled:opacity-50"
    />

    {#if open && !disabled}
        <div
            bind:this={optionsList}
            id={optionsId}
            role="listbox"
            aria-label="時刻の候補"
            class="absolute top-full right-0 left-0 z-50 mt-1 max-h-60 overflow-y-auto rounded-md border border-black/10 bg-[#313338] p-1 shadow-xl"
        >
            {#each options as option}
                <button
                    type="button"
                    role="option"
                    aria-selected={value === option}
                    class="block w-full rounded px-2 py-1.5 text-left text-sm text-[#dbdee1] hover:bg-[#404249] aria-selected:bg-[#5865f2]"
                    onclick={() => selectOption(option)}
                    onpointerdown={(event) => event.preventDefault()}
                >
                    {option}
                </button>
            {/each}
        </div>
    {/if}
</div>
