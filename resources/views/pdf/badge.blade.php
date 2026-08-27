{{--
    A single badge, sized to one page. The card and the maths that positions
    text on it live in pdf/partials/badge-card.blade.php, shared with the batch
    template (pdf/badges.blade.php) so the two can never disagree on what a
    badge looks like.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $conferenceName }} badge — {{ $name }}</title>
    <style>
        @page { margin: 0; }

        body {
            margin: 0;
            padding: 0;
            font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif;
        }

        .badge {
            position: relative;
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
@include('pdf.partials.badge-card')
</body>
</html>
