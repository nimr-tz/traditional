/**
 * The staff app's visual tokens, taken from the TMSC design canvas.
 *
 * The palette is the conference's own: NIMR green for anything actionable, a
 * darker green for headings and the selected tab, and a warm brown reserved for
 * money that has not been settled. Keeping them here rather than inline means
 * the three screens cannot drift apart, and a re-skin is one file.
 *
 * `fonts` currently names the platform's own faces. The design specifies
 * Newsreader for headings and Inter for body text; adding them needs expo-font
 * and the font binaries, so until that happens every screen reads these tokens
 * and switching is a change here rather than across every StyleSheet.
 */
export const colors = {
    /** Buttons, active accents, anything the staff member can press. */
    primary: '#0E7C42',
    /** Headings, serif titles, the selected mode tab. */
    primaryDark: '#0B3B22',

    /** Screen background — warm off-white, not grey. */
    background: '#FBF7F0',
    surface: '#FFFFFF',

    text: '#172019',
    textMuted: '#647168',
    textSubtle: '#536159',
    textFaint: '#617067',

    border: '#C8CEC9',
    borderStrong: '#BFC7C1',
    divider: '#D3D9D4',
    headerDivider: '#D7DCD8',

    /** Settled and unsettled money. Brown never means "error", only "owing". */
    paid: '#1D6B41',
    owing: '#9A5B12',
    owingSurface: '#FBF1DC',
    owingMark: '#B8863B',
    /** A scan that was already recorded today — not a failure, just a repeat. */
    duplicate: '#996817',

    danger: '#A73526',
    dangerSurface: '#F9E7E3',
    noticeText: '#1D4B32',
    noticeSurface: '#E4F1E6',

    /** The scan window's frame: yellow reads against both dark shade and daylight. */
    scanFrame: '#FFD24A',
    scanShade: 'rgba(0,0,0,0.55)',

    onPrimary: '#FFFFFF',
} as const;

export const fonts = {
    /** Headings and screen titles. Serif in the design; platform serif for now. */
    heading: undefined as string | undefined,
    body: undefined as string | undefined,
} as const;
