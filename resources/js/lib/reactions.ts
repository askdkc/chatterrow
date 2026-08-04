export const STAMP_REACTION_PREFIX = 'stamp:';
export const MAX_STAMP_TEXT_LENGTH = 4;
export const DEFAULT_STAMP_TEXTS = ['それな', 'すごい', 'たしかに'] as const;

export type StampReactionStyle = {
    textColor: string;
    backgroundColor: string | null;
};

export type StampReactionDetails = {
    text: string;
    style: StampReactionStyle | null;
};

const HEX_COLOR_PATTERN = /^#[0-9a-f]{6}$/i;
const STYLED_STAMP_PATTERN =
    /^stamp:v1:([0-9a-f]{6}):(none|[0-9a-f]{6}):([\s\S]+)$/i;

export function normalizeStampText(value: string): string {
    const normalized = value.trim().replace(/\s+/g, ' ');

    return Array.from(normalized).slice(0, MAX_STAMP_TEXT_LENGTH).join('');
}

function normalizeHexColor(value: string): string | null {
    return HEX_COLOR_PATTERN.test(value) ? value.toLowerCase() : null;
}

export function createStampReaction(
    value: string,
    style?: StampReactionStyle,
): string | null {
    const text = normalizeStampText(value);

    if (!text) {
        return null;
    }

    if (!style) {
        return `${STAMP_REACTION_PREFIX}${text}`;
    }

    const textColor = normalizeHexColor(style.textColor);
    const backgroundColor =
        style.backgroundColor === null
            ? 'none'
            : normalizeHexColor(style.backgroundColor)?.slice(1);

    if (!textColor || !backgroundColor) {
        return null;
    }

    return `${STAMP_REACTION_PREFIX}v1:${textColor.slice(1)}:${backgroundColor}:${text}`;
}

export function parseStampReaction(value: string): StampReactionDetails | null {
    const styledMatch = value.match(STYLED_STAMP_PATTERN);

    if (styledMatch) {
        const rawText = styledMatch[3];
        const text = normalizeStampText(rawText);

        if (!text || text !== rawText) {
            return null;
        }

        return {
            text,
            style: {
                textColor: `#${styledMatch[1].toLowerCase()}`,
                backgroundColor:
                    styledMatch[2].toLowerCase() === 'none'
                        ? null
                        : `#${styledMatch[2].toLowerCase()}`,
            },
        };
    }

    if (!value.startsWith(STAMP_REACTION_PREFIX)) {
        return null;
    }

    const rawText = value.slice(STAMP_REACTION_PREFIX.length);
    const text = normalizeStampText(rawText);

    return text && text === rawText ? { text, style: null } : null;
}

export function normalizeStampReaction(value: string): string | null {
    const reaction = parseStampReaction(value);

    return reaction
        ? createStampReaction(reaction.text, reaction.style ?? undefined)
        : null;
}

export function stampReactionText(value: string): string | null {
    return parseStampReaction(value)?.text ?? null;
}

export function stampReactionStyle(value: string): StampReactionStyle | null {
    return parseStampReaction(value)?.style ?? null;
}

export function isStampReaction(value: string): boolean {
    return parseStampReaction(value) !== null;
}

export function reactionDisplayLabel(value: string): string {
    const stampText = stampReactionText(value);

    return stampText ? `ハンコ「${stampText}」` : value;
}
