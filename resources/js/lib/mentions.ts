import type { MentionKind, MentionResource } from '@/types';

export type MentionToken = {
    kind: MentionKind;
    id: string | null;
    raw: string;
    start: number;
    end: number;
    offset: number;
    length: number;
};

export type MentionAnchor = {
    kind: MentionKind;
    id: number | null;
    label: string;
    start: number;
    end: number;
};

export type MentionCandidate = {
    id: number | null;
    kind: MentionKind;
    name: string;
    email: string;
};

export type MentionCursorQuery = {
    query: string;
    start: number;
    end: number;
};

type ProtectedRange = {
    start: number;
    end: number;
};

type MentionScan = {
    tokens: MentionToken[];
    protectedRanges: ProtectedRange[];
};

function runLength(value: string, position: number, character: string): number {
    let length = 0;

    while (value[position + length] === character) {
        length += 1;
    }

    return length;
}

function fenceAt(value: string, position: number): RegExpMatchArray | null {
    return value
        .slice(position)
        .match(/^[ \t]{0,3}(`{3,}|~{3,})[^\r\n]*(?:\r?\n|$)/);
}

function closingFenceAt(
    value: string,
    position: number,
    character: string,
    length: number,
): RegExpMatchArray | null {
    const marker = character.repeat(length);

    return value
        .slice(position)
        .match(
            new RegExp(
                `^[ \\t]{0,3}${marker}${character}*[ \\t]*(?:\\r?\\n|$)`,
            ),
        );
}

function scanMentions(value: string): MentionScan {
    const tokens: MentionToken[] = [];
    const protectedRanges: ProtectedRange[] = [];
    let position = 0;
    let lineStart = true;
    let fenceCharacter: string | null = null;
    let fenceLength = 0;
    let fenceStart = -1;
    let inlineLength: number | null = null;
    let inlineStart = -1;

    while (position < value.length) {
        if (fenceCharacter !== null) {
            const closing = lineStart
                ? closingFenceAt(value, position, fenceCharacter, fenceLength)
                : null;

            if (closing) {
                position += closing[0].length;
                protectedRanges.push({ start: fenceStart, end: position });
                fenceCharacter = null;
                fenceLength = 0;
                fenceStart = -1;
                lineStart = closing[0].endsWith('\n');
                continue;
            }

            lineStart = value[position] === '\n';
            position += 1;
            continue;
        }

        if (inlineLength !== null) {
            if (value[position] === '`') {
                const closingLength = runLength(value, position, '`');

                if (closingLength >= inlineLength) {
                    position += closingLength;
                    protectedRanges.push({
                        start: inlineStart,
                        end: position,
                    });
                    inlineLength = null;
                    inlineStart = -1;
                    continue;
                }
            }

            lineStart = value[position] === '\n';
            position += 1;
            continue;
        }

        if (lineStart) {
            const fence = fenceAt(value, position);

            if (fence) {
                const marker = fence[1] ?? '';
                fenceCharacter = marker[0] ?? null;
                fenceLength = marker.length;
                fenceStart = position;
                position += fence[0].length;
                lineStart = fence[0].endsWith('\n');
                continue;
            }
        }

        if (value[position] === '`') {
            inlineLength = runLength(value, position, '`');
            inlineStart = position;
            position += inlineLength;
            lineStart = false;
            continue;
        }

        const remaining = value.slice(position);
        const everyone = remaining.match(/^<!everyone>/);

        if (everyone) {
            const raw = everyone[0];
            const start = position;
            position += raw.length;
            tokens.push({
                kind: 'everyone',
                id: null,
                raw,
                start,
                end: position,
                offset: start,
                length: raw.length,
            });
            lineStart = false;
            continue;
        }

        const direct = remaining.match(/^<@([^>\r\n]*)>/);

        if (direct) {
            const raw = direct[0];
            const start = position;
            position += raw.length;
            tokens.push({
                kind: 'direct',
                id: direct[1] || null,
                raw,
                start,
                end: position,
                offset: start,
                length: raw.length,
            });
            lineStart = false;
            continue;
        }

        lineStart = value[position] === '\n';
        position += 1;
    }

    if (fenceCharacter !== null && fenceStart >= 0) {
        protectedRanges.push({ start: fenceStart, end: value.length });
    }

    if (inlineLength !== null && inlineStart >= 0) {
        protectedRanges.push({ start: inlineStart, end: value.length });
    }

    return { tokens, protectedRanges };
}

/** Parse mention tokens while excluding fenced and inline code. */
export function parseMentionTokens(value: string): MentionToken[] {
    return scanMentions(value).tokens;
}

export const parseMentions = parseMentionTokens;

function isWordCharacter(value: string): boolean {
    return /[\p{L}\p{N}_]/u.test(value);
}

function isQueryCharacter(value: string): boolean {
    return /[\p{L}\p{N}._+-]/u.test(value);
}

function isInsideProtectedRange(
    ranges: ProtectedRange[],
    position: number,
): boolean {
    return ranges.some(
        (range) => position >= range.start && position < range.end,
    );
}

/** Find the @query immediately before a composer cursor. */
export function findMentionQuery(
    value: string,
    cursor: number,
): MentionCursorQuery | null {
    const position = Math.max(0, Math.min(cursor, value.length));
    const { protectedRanges } = scanMentions(value);

    if (isInsideProtectedRange(protectedRanges, position)) {
        return null;
    }

    const lineStart = value.lastIndexOf('\n', position - 1) + 1;
    let at = position - 1;

    while (at >= lineStart) {
        if (value[at] === '@') {
            const previous = value[at - 1];

            if (previous && (isWordCharacter(previous) || previous === '@')) {
                at -= 1;
                continue;
            }

            const query = value.slice(at + 1, position);

            if (
                [...query].every(isQueryCharacter) &&
                !isInsideProtectedRange(protectedRanges, at)
            ) {
                return { query, start: at, end: position };
            }

            return null;
        }

        if (!isQueryCharacter(value[at])) {
            break;
        }

        at -= 1;
    }

    return null;
}

export const getMentionQuery = findMentionQuery;

function displayNameForMention(
    mention: Pick<MentionResource, 'kind' | 'name'> | MentionCandidate,
): string {
    if (mention.kind === 'everyone') {
        return 'everyone';
    }

    return mention.name.trim().replace(/\s+/g, ' ') || 'deleted user';
}

export function mentionDisplayText(
    mention: Pick<MentionResource, 'kind' | 'name'> | MentionCandidate,
): string {
    return `@${displayNameForMention(mention)}`;
}

function mentionTokenForAnchor(anchor: MentionAnchor): string | null {
    if (anchor.kind === 'everyone') {
        return '<!everyone>';
    }

    return anchor.id !== null &&
        Number.isSafeInteger(anchor.id) &&
        anchor.id > 0
        ? `<@${anchor.id}>`
        : null;
}

function isValidAnchor(value: string, anchor: MentionAnchor): boolean {
    return (
        anchor.start >= 0 &&
        anchor.end > anchor.start &&
        anchor.end <= value.length &&
        value.slice(anchor.start, anchor.end) === anchor.label
    );
}

/** Replace a composer query and shift existing anchors around it. */
export function replaceMentionRange(
    value: string,
    range: Pick<MentionCursorQuery, 'start' | 'end'>,
    mention: MentionCandidate,
    anchors: readonly MentionAnchor[] = [],
): {
    value: string;
    anchors: MentionAnchor[];
    cursor: number;
} {
    const start = Math.max(0, Math.min(range.start, value.length));
    const end = Math.max(start, Math.min(range.end, value.length));
    const label = mentionDisplayText(mention);
    const replacement = `${label} `;
    const delta = replacement.length - (end - start);
    const shifted = anchors
        .filter((anchor) => isValidAnchor(value, anchor))
        .flatMap((anchor) => {
            if (anchor.end <= start) {
                return [anchor];
            }

            if (anchor.start >= end) {
                return [
                    {
                        ...anchor,
                        start: anchor.start + delta,
                        end: anchor.end + delta,
                    },
                ];
            }

            return [];
        });
    const nextAnchor: MentionAnchor = {
        kind: mention.kind,
        id: mention.id,
        label,
        start,
        end: start + label.length,
    };

    return {
        value: `${value.slice(0, start)}${replacement}${value.slice(end)}`,
        anchors: [...shifted, nextAnchor].sort((a, b) => a.start - b.start),
        cursor: start + replacement.length,
    };
}

export const insertMentionAtRange = replaceMentionRange;

/** Serialize only anchors that still exactly match their selected label. */
export function serializeDraftMentions(
    value: string,
    anchors: readonly MentionAnchor[],
): string {
    const replacements = anchors
        .filter((anchor) => isValidAnchor(value, anchor))
        .map((anchor) => ({
            anchor,
            token: mentionTokenForAnchor(anchor),
        }))
        .filter(
            (
                replacement,
            ): replacement is {
                anchor: MentionAnchor;
                token: string;
            } => replacement.token !== null,
        )
        .sort((a, b) => b.anchor.start - a.anchor.start);
    let serialized = value;

    for (const { anchor, token } of replacements) {
        serialized = `${serialized.slice(0, anchor.start)}${token}${serialized.slice(anchor.end)}`;
    }

    return serialized;
}

export const serializeMentionsForSubmit = serializeDraftMentions;
export const serializeMentions = serializeDraftMentions;

/** Keep anchors that survive a textarea edit and shift their ranges. */
export function updateMentionAnchors(
    previousValue: string,
    nextValue: string,
    anchors: readonly MentionAnchor[],
): MentionAnchor[] {
    if (previousValue === nextValue) {
        return anchors.filter((anchor) => isValidAnchor(nextValue, anchor));
    }

    let prefix = 0;

    while (
        prefix < previousValue.length &&
        prefix < nextValue.length &&
        previousValue[prefix] === nextValue[prefix]
    ) {
        prefix += 1;
    }

    let suffix = 0;

    while (
        suffix < previousValue.length - prefix &&
        suffix < nextValue.length - prefix &&
        previousValue[previousValue.length - suffix - 1] ===
            nextValue[nextValue.length - suffix - 1]
    ) {
        suffix += 1;
    }

    const previousChangeEnd = previousValue.length - suffix;
    const nextChangeEnd = nextValue.length - suffix;
    const delta = nextChangeEnd - previousChangeEnd;

    return anchors
        .filter((anchor) => {
            return anchor.end <= prefix || anchor.start >= previousChangeEnd;
        })
        .map((anchor) => {
            if (anchor.start >= previousChangeEnd) {
                return {
                    ...anchor,
                    start: anchor.start + delta,
                    end: anchor.end + delta,
                };
            }

            return anchor;
        })
        .filter((anchor) => isValidAnchor(nextValue, anchor));
}

export const updateDraftMentionAnchors = updateMentionAnchors;

function resolvedMentionMap(
    mentions: readonly MentionResource[] = [],
): Map<string, MentionResource> {
    return new Map(
        mentions
            .filter((mention) => mention.kind === 'direct')
            .map((mention) => [String(mention.id), mention]),
    );
}

function safeTokenText(
    token: MentionToken,
    mentions: Map<string, MentionResource>,
): string {
    if (token.kind === 'everyone') {
        return '@everyone';
    }

    const mention = token.id === null ? undefined : mentions.get(token.id);

    return mention ? mentionDisplayText(mention) : '[deleted user]';
}

function replaceTokenText(
    value: string,
    mentions: readonly MentionResource[],
): { value: string; anchors: MentionAnchor[] } {
    const tokens = parseMentionTokens(value);
    const resolved = resolvedMentionMap(mentions);
    let cursor = 0;
    let restored = '';
    const anchors: MentionAnchor[] = [];

    for (const token of tokens) {
        restored += value.slice(cursor, token.start);
        const text = safeTokenText(token, resolved);
        const start = restored.length;
        restored += text;

        if (token.kind === 'everyone' || resolved.has(token.id ?? '')) {
            const mention =
                token.kind === 'everyone'
                    ? { id: null, kind: 'everyone' as const, name: 'everyone' }
                    : resolved.get(token.id ?? '');

            if (mention) {
                anchors.push({
                    kind: mention.kind,
                    id: mention.kind === 'everyone' ? null : mention.id,
                    label: text,
                    start,
                    end: restored.length,
                });
            }
        }

        cursor = token.end;
    }

    restored += value.slice(cursor);

    return { value: restored, anchors };
}

/** Restore token bodies to editable display-name text and retain anchors. */
export function restoreDraftMentions(
    value: string,
    mentions: readonly MentionResource[] = [],
): { value: string; anchors: MentionAnchor[] } {
    return replaceTokenText(value, mentions);
}

/** Restore mentions for an edit form without exposing unresolved IDs. */
export function restoreMentionsForEditing(
    value: string,
    mentions: readonly MentionResource[] = [],
): string {
    return restoreDraftMentions(value, mentions).value;
}

export const restoreMentionNames = restoreMentionsForEditing;

/** Render token bodies as safe plain text for excerpts and thread titles. */
export function safeMentionText(
    value: string,
    mentions: readonly MentionResource[] = [],
): string {
    return replaceTokenText(value, mentions).value;
}

export const renderMentionText = safeMentionText;
