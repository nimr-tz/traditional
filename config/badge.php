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
    | Vertical budget for the white field (38.05% – 95.82%):
    |
    | Vertical budget, worked from the top down so every gap is deliberate:
    |
    |   1.2%   clear of the green curve
    |   name   sits at 50.1%, over one or two lines, growing upwards
    |   4.5%   gap — the name and the institution are separate facts
    |   inst   sits at 61.2%, over one or two lines
    |   2.2%   gap
    |   qr     63.4% – 92.8%
    |   3.0%   clear of the flag bar
    |
    | There is deliberately no category band and no registration code: the code
    | is already carried by the QR, and a fee tier tells a door steward nothing
    | they would act on.
    */
    'placeholders' => [
        'name' => [
            'left' => '8%',
            // Sitting at 50.1%, growing upwards. Two lines at full size reach
            // ~39.3%, clearing the green header's curve at 38.05%.
            'bottom' => '49.9%',
            'width' => '84%',
            'font_size' => '6.5mm',
            /*
             * Like the institution, sized for up to TWO lines rather than forced
             * onto one. Holding a 32-character name on a single line meant 2.9mm
             * type — smaller than the institution beneath it, which is backwards.
             * Wrapping instead keeps that same name at 5.5mm.
             *
             * A short name still gets one line: 6.5mm fits ~16 characters across,
             * so it only wraps once it has to. Thresholds are the two-line
             * capacity — ~67.9mm of width, about 0.63 × font-size per character,
             * doubled, with ~10% headroom.
             */
            'shrink_at' => [29 => '5.5mm', 35 => '4.6mm', 42 => '3.8mm', 51 => '3.2mm'],
            'line_height' => '1.15',
            'letter_spacing' => '0.02em',
            'color' => '#132986',
            'align' => 'center',
            'transform' => 'uppercase',
            'edge_offset_mm' => 0.13,
        ],

        'institution' => [
            'left' => '8%',
            // Sitting at 61.2%, over one or two lines, which leaves ~6mm of white
            // between it and the name above — enough that the two read as
            // separate facts rather than one block. Never larger than the name:
            // the blade and the React component both clamp it.
            'bottom' => '38.8%',
            'width' => '84%',
            'font_size' => '3.6mm',
            /*
             * Sized for two lines, not one. Squeezing "National Institute for
             * Medical Research (NIMR)" onto a single line meant 2mm type — legible
             * on paper held at arm's length, but far too small next to a 6mm name.
             * Wrapping to a second line instead keeps it at 3.6mm.
             *
             * The thresholds are therefore roughly double the one-line capacity:
             * ~67.9mm of width at about 0.72 × font-size per character, times two
             * lines. Only an institution too long for two lines steps down.
             */
            'shrink_at' => [52 => '3.2mm', 59 => '2.8mm', 67 => '2.4mm', 78 => '2mm'],
            'line_height' => '1.25',
            'letter_spacing' => '0.04em',
            'color' => '#96500b',
            'align' => 'center',
            'transform' => 'uppercase',
            'edge_offset_mm' => 0.1,
        ],

        /*
        | Large, but no longer as large as the canvas allows. 50% of 80.88mm is a
        | ~40mm square — still 2.6× the 15.4mm the first template managed — and
        | what it gave back is what lets the name run to 6.5mm over two lines
        | instead of collapsing to 2.9mm on one, and buys the ~6mm of air between
        | the name and the institution. A steward reads the name across a room
        | and scans the QR from a few centimetres away, so the trade favours the
        | name.
        |
        | The QR is square, so its height follows its width. Widening it means
        | moving `top` up by the same amount in percent-of-height terms, or it
        | will run into the flag bar.
        */
        'qr' => [
            'left' => '25%',
            'top' => '63.4%',
            'width' => '50%',
        ],
    ],
];
