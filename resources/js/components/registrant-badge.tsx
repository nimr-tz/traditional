/**
 * The badge on screen, positioned from the same numbers as the printed one.
 *
 * The PDF is artwork with text stamped on top at percentage coordinates from
 * config/badge.php; the server sends those same percentages here, so re-skinning
 * the badge moves both at once rather than leaving this copy behind. Sizes are
 * the config's own millimetres, which CSS understands, so the card on screen is
 * life-size — hold a printed badge against the monitor and they match.
 *
 * Two deliberate differences from resources/views/pdf/badge.blade.php: bold is a
 * font weight rather than the same string stamped at four sub-millimetre offsets
 * (that trick exists only because dompdf renders synthetic bold badly), and the
 * QR is a plain <img> since nothing here has to survive a PDF renderer.
 */

interface Placeholder {
    left: string;
    /** Text blocks anchor by their bottom edge so they sit on the artwork's rule; the QR uses top. */
    top?: string;
    bottom?: string;
    width: string;
    font_size?: string;
    shrink_at?: Record<string, string>;
    line_height?: string;
    letter_spacing?: string;
    color?: string;
    align?: string;
    transform?: string;
}

export interface BadgeContent {
    name: string;
    institution: string | null;
    registrationCode: string | null;
    /** Data URI. Encodes the verification URL, so this square is scannable off the screen. */
    qr: string;
    conferenceName: string | null;
    conferenceYear: string | null;
    template: { background: string; width_mm: number; height_mm: number };
    placeholders: {
        name: Placeholder;
        institution: Placeholder;
        qr: Placeholder;
    };
    background: string;
}

const tidy = (value: string | null): string => (value ?? '').replace(/\s+/g, ' ').trim();

/** Long names step down through the configured sizes rather than wrapping into the line below. */
function sizeFor(spec: Placeholder, text: string): string | undefined {
    let size = spec.font_size;

    for (const [threshold, smaller] of Object.entries(spec.shrink_at ?? {})) {
        if (text.length > Number(threshold)) size = smaller;
    }

    return size;
}

/**
 * The institution may never be set in larger type than the name.
 *
 * The two shrink independently — the name to stay on one line, the institution
 * to fit two — so a long name with a short institution would otherwise leave the
 * institution the biggest thing on the badge. Mirrors the same clamp in
 * resources/views/pdf/badge.blade.php.
 */
function capToName(size: string | undefined, nameSize: string | undefined): string | undefined {
    if (!size || !nameSize) return size;

    return parseFloat(size) > parseFloat(nameSize) ? nameSize : size;
}

function stampStyle(spec: Placeholder, fontSize?: string): React.CSSProperties {
    return {
        position: 'absolute',
        left: spec.left,
        ...(spec.bottom !== undefined ? { bottom: spec.bottom } : { top: spec.top }),
        width: spec.width,
        fontSize: fontSize ?? spec.font_size ?? '3mm',
        lineHeight: spec.line_height ?? '1.2',
        letterSpacing: spec.letter_spacing ?? '0',
        color: spec.color ?? '#000000',
        textAlign: (spec.align ?? 'center') as React.CSSProperties['textAlign'],
        textTransform: (spec.transform ?? 'none') as React.CSSProperties['textTransform'],
        fontWeight: 700,
    };
}

export function RegistrantBadge({ badge, className }: { badge: BadgeContent; className?: string }) {
    const name = tidy(badge.name);
    const institution = tidy(badge.institution);
    const { placeholders: place, template } = badge;
    const nameSize = sizeFor(place.name, name);

    return (
        <div
            className={className}
            style={{
                position: 'relative',
                width: `${template.width_mm}mm`,
                height: `${template.height_mm}mm`,
                overflow: 'hidden',
                fontFamily: "'DejaVu Sans', Helvetica, Arial, sans-serif",
                background: '#ffffff',
            }}
        >
            {/* The artwork is the design. If it is missing the badge still reads,
                because a desk with an unbranded badge is better off than one with none. */}
            <img
                src={badge.background}
                alt=""
                style={{ position: 'absolute', inset: 0, width: '100%', height: '100%' }}
                onError={(event) => {
                    event.currentTarget.style.display = 'none';
                }}
            />

            <div style={stampStyle(place.name, nameSize)}>{name}</div>

            {institution && (
                <div style={stampStyle(place.institution, capToName(sizeFor(place.institution, institution), nameSize))}>{institution}</div>
            )}

            <div style={{ position: 'absolute', left: place.qr.left, top: place.qr.top, width: place.qr.width }}>
                <img src={badge.qr} alt={`Badge QR code for ${name}`} style={{ width: '100%', display: 'block' }} />
            </div>
        </div>
    );
}
