<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Badge artwork and layout
    |--------------------------------------------------------------------------
    |
    | The finished artwork is the whole design. The PDF stamps only the dynamic
    | text — name, institution, QR — on top of it at percentage coordinates, so
    | re-skinning the badge is an image swap plus a nudge to the numbers below,
    | not a template rewrite.
    |
    | Unlike the fill-in template that came before it, this artwork is a blank
    | canvas: a green header, a plain white field, and the flag bar. There are no
    | printed rules or boxes to line anything up with, so the numbers below are
    | a layout rather than a tracing. What the artwork does fix is the usable
    | area, measured off the image —
    |
    |   green header ends   38.05%   (its curve dips lowest at the centre)
    |   flag bar begins     95.82%
    |
    | Everything must live between those two, edge to edge.
    |
    | ⚠ The conference name, edition, venue and date are drawn into the artwork
    | ("5TH … CONFERENCE AND EXHIBITION", "MBEYA. 28 AUGUST 2026"). Changing any
    | of those in conference settings will NOT move the badge — that needs a new
    | export of the image.
    |
    */

    'template' => [
        'background' => 'images/Traditional Medicine Scientific Conference.png',

        // The artwork is 591 × 1004px, an aspect ratio of 0.58865 — which is
        // exactly 80.88 × 137.4mm. These follow the image rather than the badge
        // stock on purpose: matching the artwork means it is never stretched,
        // and a mismatch with the holder is a visible margin rather than a
        // distorted logo.
        //
        // ⚠ 591px across 80.88mm is only ~186dpi. That prints acceptably but is
        // below the 300dpi print standard; a 955 × 1622px export of the same
        // design would drop straight in here with no other change.
        'width_mm' => 80.88,
        'height_mm' => 137.4,
    ],

    /*
    | The most badges the venue desk will render into a single batch PDF. The
    | desk prints a run for whoever matches the current filter; without a ceiling
    | an unfiltered "print everyone" is a several-hundred-page document that
    | dompdf takes minutes over. Past this, the desk is told to narrow the view.
    */
    'batch_limit' => (int) env('BADGE_BATCH_LIMIT', 300),

    /*
    | Placeholder geometry, as percentages of the badge. `left`/`width` position
    | the box; text is centred within its own width.
    |
    | dompdf renders font-weight poorly, so bold text is faked by stamping the
    | same string several times at sub-millimetre offsets (see the blade). Keep
    | `edge_offset_mm` small — larger values read as a blur, not a bold.
    |
    | The text blocks are anchored by `bottom`, not `top`. With a blank canvas
    | that matters less than it did against a printed rule, but it still means a
    | value long enough to wrap grows upwards into empty white instead of down
    | into the QR. The `shrink_at` steps aim to keep everything on one line
    | anyway; they are sized from this font's real width, about 0.63 × font-size
    | per uppercase character, against the 67.9mm these blocks have to work with.
    |
    | Vertical budget for the white field (38.05% – 95.82%), worked from the top
    | down so every gap is deliberate:
    |
    |   1.2%   clear of the green curve
    |   name   sits at 54%, over one to three lines, growing upwards
    |   ~5.5%  gap — the name and the institution are separate facts
    |   inst   sits at 67% (4.2mm), over one to three lines
    |   ~3%    gap
    |   qr     70% – 92.4%   (smaller and lower than it was, to give the text room)
    |   3.4%   clear of the flag bar
    |
    | There is deliberately no category band and no registration code: the code
    | is already carried by the QR, and a fee tier tells a door steward nothing
    | they would act on.
    */
    'placeholders' => [
        'name' => [
            'left' => '8%',
            // Baseline at 54% from the top, growing upwards. Dropped here (from
            // ~50%) once the QR was pulled down and shrunk: the name now has the
            // middle of the card, not a sliver above the code. A two-line name
            // at 7mm tops out around 42%, well clear of the green curve at
            // 38.05%; a three-line name has shrunk to 5.5mm first and clears too.
            'bottom' => '46%',
            'width' => '84%',
            'font_size' => '7mm',
            /*
             * Sized for up to TWO lines at the base size, then it steps down —
             * three lines at 7mm would reach the green curve. A short name gets
             * one line; ~16 characters fit across at 7mm before it wraps.
             * Thresholds are the two-line capacity — ~67.9mm of width, about
             * 0.63 × font-size per character, doubled, with ~10% headroom.
             */
            'shrink_at' => [31 => '5.5mm', 38 => '4.6mm', 46 => '3.9mm', 55 => '3.3mm'],
            'line_height' => '1.13',
            'letter_spacing' => '0.02em',
            'color' => '#132986',
            'align' => 'center',
            'transform' => 'uppercase',
            'edge_offset_mm' => 0.13,
        ],

        'institution' => [
            'left' => '8%',
            // Baseline at 67% from the top — down in the band the QR gave back —
            // leaving a clear ~7mm of white above it so it and the name read as
            // separate facts. Never larger than the name: the blade and the
            // React component both clamp it. Also carries a dignitary's role
            // ("DIRECTOR GENERAL, MUHAS"), which is why it runs at 4.2mm.
            'bottom' => '33%',
            'width' => '84%',
            'font_size' => '4.2mm',
            /*
             * Sized for two lines, not one. Squeezing "National Institute for
             * Medical Research (NIMR)" onto a single line meant 2mm type — legible
             * on paper held at arm's length, but far too small next to a 6mm name.
             * Wrapping to a second line instead keeps it at 4.2mm.
             *
             * The thresholds are therefore roughly double the one-line capacity:
             * ~67.9mm of width at about 0.72 × font-size per character, times two
             * lines. Only an institution too long for two lines steps down. Steps
             * scaled with the base bump so a long affiliation still lands on two
             * lines rather than three.
             */
            'shrink_at' => [52 => '3.7mm', 59 => '3.3mm', 67 => '2.9mm', 78 => '2.4mm'],
            'line_height' => '1.25',
            'letter_spacing' => '0.04em',
            // Dark slate, not the old ochre: the navy name stays the one accent
            // on the card and the affiliation reads as clearly secondary,
            // without the muddiness the brown had on the white field.
            'color' => '#334155',
            'align' => 'center',
            'transform' => 'uppercase',
            'edge_offset_mm' => 0.12,
        ],

        /*
        | Pulled down and shrunk. It was a ~40mm square starting at 63.4%, which
        | crammed the name and a multi-line title into the sliver above it. At
        | 38% of 80.88mm it is a ~30.7mm square — still ~2× the 15.4mm the first
        | template managed, and still read from a few centimetres away — starting
        | at 70%, which hands ~13% of card height back to the text. A steward
        | reads the name across a room and scans the QR up close, so the trade
        | favours the name.
        |
        | The QR is square, so its height follows its width. Widening it means
        | moving `top` up by the same amount in percent-of-height terms, or it
        | will run into the flag bar at 95.82%.
        */
        'qr' => [
            'left' => '31%',
            'top' => '70%',
            'width' => '38%',
        ],
    ],
];
