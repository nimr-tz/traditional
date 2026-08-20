/**
 * Working out what a text message will actually cost to send.
 *
 * SMS is billed per part, and the size of a part depends on the alphabet the
 * message needs. Plain text uses GSM-7 and fits 160 characters. A single
 * character outside that alphabet — a curly apostrophe pasted from Word, an
 * emoji, an accented name — forces the whole message into UCS-2, where a part
 * is only 70 characters. A 300-character announcement priced at two parts
 * becomes five, for every recipient, and nothing on screen would say why.
 */

/** Characters GSM-7 encodes in a single unit. */
const GSM7_BASIC =
    '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞÆæßÉ !"#¤%&\'()*+,-./0123456789:;<=>?' + '¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà';

/** Characters GSM-7 can encode, but which occupy two units each. */
const GSM7_EXTENDED = '^{}\\[~]|€';

const GSM7_SINGLE_PART = 160;
const GSM7_MULTI_PART = 153;
const UCS2_SINGLE_PART = 70;
const UCS2_MULTI_PART = 67;

export type SmsEncoding = 'GSM-7' | 'UCS-2';

export interface SmsCost {
    encoding: SmsEncoding;
    /** Billable units, which is not the same as `text.length` for extended characters. */
    units: number;
    parts: number;
    /** Units left before another part is charged. */
    remaining: number;
    /** The distinct characters that forced UCS-2, for showing the admin what to replace. */
    offenders: string[];
}

export function smsCost(text: string): SmsCost {
    const characters = Array.from(text);
    const offenders: string[] = [];
    let units = 0;

    for (const character of characters) {
        if (GSM7_BASIC.includes(character)) {
            units += 1;
        } else if (GSM7_EXTENDED.includes(character)) {
            units += 2;
        } else {
            if (!offenders.includes(character)) offenders.push(character);
            units += 1;
        }
    }

    const encoding: SmsEncoding = offenders.length > 0 ? 'UCS-2' : 'GSM-7';

    if (encoding === 'UCS-2') {
        // UCS-2 counts UTF-16 code units, so an emoji outside the basic plane
        // costs two on its own.
        units = text.length;
    }

    const single = encoding === 'GSM-7' ? GSM7_SINGLE_PART : UCS2_SINGLE_PART;
    const multi = encoding === 'GSM-7' ? GSM7_MULTI_PART : UCS2_MULTI_PART;

    const parts = units === 0 ? 0 : units <= single ? 1 : Math.ceil(units / multi);
    const capacity = parts <= 1 ? single : parts * multi;

    return { encoding, units, parts, remaining: Math.max(capacity - units, 0), offenders };
}

/**
 * Most non-GSM characters admins actually hit come from pasting out of Word,
 * which silently converts quotes and dashes. These have exact plain
 * equivalents, so they can be swapped without changing a word of the message.
 */
const TYPOGRAPHIC_REPLACEMENTS: Array<[RegExp, string]> = [
    [/[‘’‚‛]/g, "'"],
    [/[“”„‟]/g, '"'],
    [/[–—―]/g, '-'],
    [/…/g, '...'],
    // Non-breaking space, written as an escape so it stays visible in review.
    [/\u00a0/g, ' '],
    [/[•·]/g, '-'],
];

export function fixTypography(text: string): string {
    return TYPOGRAPHIC_REPLACEMENTS.reduce((result, [pattern, replacement]) => result.replace(pattern, replacement), text);
}

/** Whether swapping typographic characters would actually help this message. */
export function hasFixableTypography(text: string): boolean {
    return fixTypography(text) !== text;
}
