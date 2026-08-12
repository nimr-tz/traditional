<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Badge artwork and layout
    |--------------------------------------------------------------------------
    |
    | The finished artwork is the whole design. The PDF stamps only the dynamic
    | text — name, institution, category, QR — on top of it at percentage
    | coordinates, so re-skinning the badge is an image swap plus a nudge to the
    | numbers below, not a template rewrite.
    |
    | ⚠ The shipped artwork is a PLACEHOLDER borrowed from AJSC. It reads
    | "33rd Annual Joint Scientific Conference · 9–11 June 2026" — the wrong
    | conference and the wrong dates. Replace `background` with the real TMSC
    | artwork before printing anything anyone will wear, and re-check the
    | placeholder positions against it.
    |
    */

    'template' => [
        'background' => 'images/badge-template-placeholder.png',

        // Physical badge stock, in millimetres. The artwork's aspect ratio
        // should match, or it will be stretched.
        'width_mm' => 80.88,
        'height_mm' => 137.4,
    ],

    /*
    | Placeholder geometry, as percentages of the badge. `top`/`left` position
    | the box; text is centred within its own width.
    |
    | dompdf renders font-weight poorly, so bold text is faked by stamping the
    | same string several times at sub-millimetre offsets (see the blade). Keep
    | `edge_offset_mm` small — larger values read as a blur, not a bold.
    */
    /*
    | Vertical budget for the white panel below the artwork's wave, which runs
    | from roughly 50% to 90% of the badge. Each block is placed with room for
    | its *longest realistic* content, not its shortest: "Muhimbili University
    | of Health and Allied Sciences" takes two lines however hard it shrinks,
    | and the first layout let that second line land on top of the QR.
    |
    |   name         55.0%  → up to 2 lines
    |   institution  66.5%  → up to 2 lines
    |   category     76.5%
    |   qr           80.5%  → square, ~11% tall
    |   code         93.0%
    */
    'placeholders' => [
        'name' => [
            'left' => '5%',
            'top' => '55%',
            'width' => '90%',
            'font_size' => '5.3mm',
            // Long names step down through these sizes rather than wrapping
            // into the institution line below.
            'shrink_at' => [22 => '4.4mm', 30 => '3.7mm', 40 => '3.1mm'],
            'line_height' => '1.15',
            'letter_spacing' => '0.02em',
            'color' => '#132986',
            'align' => 'center',
            'transform' => 'uppercase',
            'edge_offset_mm' => 0.13,
        ],

        'institution' => [
            'left' => '8%',
            'top' => '66.5%',
            'width' => '84%',
            'font_size' => '3.4mm',
            'shrink_at' => [28 => '2.9mm', 44 => '2.5mm', 64 => '2.2mm'],
            'line_height' => '1.25',
            'letter_spacing' => '0.04em',
            'color' => '#96500b',
            'align' => 'center',
            'transform' => 'uppercase',
            'edge_offset_mm' => 0.1,
        ],

        /*
        | The category band. It carries real operational weight: a waived
        | attendee's badge has to say *how* they qualify — Media, Secretariat,
        | Invited Guest — so a door steward can tell at a glance who is entitled
        | to be there without a paid registration.
        */
        'category' => [
            'left' => '10%',
            'top' => '76.5%',
            'width' => '80%',
            'font_size' => '2.9mm',
            'line_height' => '1.2',
            'letter_spacing' => '0.14em',
            'color' => '#1b2f86',
            'align' => 'center',
            'transform' => 'uppercase',
            'edge_offset_mm' => 0.08,
        ],

        /*
        | The QR is square, so its height follows its width: 21% of an 80.88mm
        | badge is ~17mm, which is ~12.4% of the 137.4mm height. `top` plus that
        | must clear the code line below, or the two overlap — which is exactly
        | what a wider QR did on the first print.
        */
        'qr' => [
            'left' => '40.5%',
            'top' => '80.5%',
            'width' => '19%',
        ],

        'code' => [
            'left' => '10%',
            'top' => '93%',
            'width' => '80%',
            'font_size' => '2.2mm',
            'letter_spacing' => '0.1em',
            // Dark enough to stay legible where the artwork's lower wave turns
            // the background blue.
            'color' => '#3f4d5f',
            'align' => 'center',
        ],
    ],
];
