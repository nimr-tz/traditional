{{--
    A batch of badges — one card per page — for the venue desk printing a run
    before doors open. The card itself is pdf/partials/badge-card.blade.php,
    shared with the single-badge template so a re-skin moves both at once.

    Each card sits in its own .badge-sheet sized in millimetres: dompdf resolves
    the card's percentage-positioned text against that box, and a page break
    before every sheet but the first keeps one badge to a page.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ data_get($badges, '0.conferenceName', config('app.name')) }} badges — {{ count($badges) }}</title>
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            padding: 0;
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
        }

        /*
         * Half a millimetre under the true badge height ({{ $heightMm }}mm), and
         * the same amount under the paper. A flow block sized to exactly the page
         * height rounds over it in dompdf and spills a blank page after every
         * badge; the shortfall is 0.36% of the card, well below anything the eye
         * or the badge holder will register. font-size/line-height zero the
         * baseline of the whitespace Blade leaves between the tags, which would
         * otherwise stack on top of that.
         */
        .badge-sheet {
            position: relative;
            width: {{ $widthMm }}mm;
            height: {{ $heightMm - 0.5 }}mm;
            overflow: hidden;
            font-size: 0;
            line-height: 0;
        }

        /* break BEFORE every sheet but the first, not AFTER every sheet but the
           last: an "after" break on a sheet that already fills its page makes
           dompdf emit a blank page between each badge. */
        .badge-sheet.break {
            page-break-before: always;
        }

        .badge {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
        }

        .badge-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }

        /* Fallback when the artwork is missing: a plain card still prints,
           because a desk with no badge at all is worse than an unbranded one. */
        .badge-plain {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #ffffff;
            border: 3mm solid #135eeb;
        }

        .badge-plain-title {
            position: absolute;
            top: 8%;
            left: 6%;
            width: 88%;
            text-align: center;
            font-size: 4mm;
            font-weight: bold;
            color: #135eeb;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .stamp {
            position: absolute;
            display: block;
        }

        .qr {
            position: absolute;
        }

        .qr img {
            width: 100%;
        }
    </style>
</head>
<body>
@foreach ($badges as $badge)
    <div class="badge-sheet {{ $loop->first ? '' : 'break' }}">
        @include('pdf.partials.badge-card', $badge)
    </div>
@endforeach
</body>
</html>
