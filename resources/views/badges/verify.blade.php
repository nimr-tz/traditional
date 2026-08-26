@php
    /**
     * Written to be read years after the conference, by the person who kept the
     * badge or by somebody checking their CV. Everything on it is a fact that
     * still means something then: who, which conference, where, when.
     *
     * The wording follows the evidence. Somebody who was scanned in "attended";
     * somebody who registered but never came through the door "was registered
     * for". Nothing here will confirm the stronger claim on thinner evidence.
     */
    $attended = $valid && $registrant['days_attended'] > 0;

    $dates = null;
    if ($conference['start_date']) {
        try {
            $start = \Carbon\CarbonImmutable::parse($conference['start_date']);
            $end = $conference['end_date'] ? \Carbon\CarbonImmutable::parse($conference['end_date']) : null;

            $dates = $end && ! $end->isSameDay($start)
                ? $start->translatedFormat('j').'–'.$end->translatedFormat('j F Y')
                : $start->translatedFormat('j F Y');
        } catch (\Throwable) {
            $dates = null;
        }
    }

    $title = ($conference['edition'] ? $conference['edition'].' ' : '').$conference['name'];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- A badge scan should not end up in a search index. --}}
    <meta name="robots" content="noindex, nofollow">
    {{-- The tab title makes the same claim as the page, never a stronger one. --}}
    <title>{{ $valid ? $registrant['name'].($attended ? ' · Verified participation' : ' · Verified registration') : 'Badge not recognised' }} · {{ $conference['name'] }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --green: #0E7C42;
            --green-deep: #0B3B22;
            --gold: #A97C2E;
            --page: #EFEAE1;
            --card: #FFFFFF;
            --ink: #172019;
            --muted: #5C6B61;
            --faint: #7C8A81;
            --rule: #DCE3DC;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 16px;
            background: var(--page);
            color: var(--ink);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
        }

        .card {
            width: 100%;
            max-width: 440px;
            background: var(--card);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 48px rgba(11, 59, 34, 0.14);
        }

        /* The issuing institution comes first: the page is only worth anything
           because of who stands behind it. */
        .issuer {
            background: var(--green);
            color: #FFFFFF;
            padding: 22px 24px 20px;
            text-align: center;
        }

        .issuer img {
            width: 58px;
            height: 58px;
            border-radius: 50%;
            background: #FFFFFF;
            display: block;
            margin: 0 auto 12px;
        }

        .issuer .ministry {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.16em;
            text-transform: uppercase;
        }

        .issuer .institute { margin: 4px 0 0; font-size: 13px; opacity: 0.92; }

        .body { padding: 26px 26px 22px; text-align: center; }

        .attestation {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: var(--gold);
        }

        .name {
            margin: 12px 0 0;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 27px;
            font-weight: 700;
            line-height: 1.2;
            color: var(--green-deep);
        }

        .institution { margin: 8px 0 0; color: var(--muted); font-size: 15px; }

        .lead { margin: 20px 0 0; color: var(--muted); font-size: 14px; }

        .conference {
            margin: 8px 0 0;
            font-family: Georgia, 'Times New Roman', serif;
            font-size: 18px;
            font-weight: 700;
            line-height: 1.35;
            color: var(--ink);
        }

        .where { margin: 10px 0 0; color: var(--muted); font-size: 14px; }

        .days {
            display: inline-block;
            margin-top: 18px;
            padding: 7px 16px;
            border-radius: 999px;
            background: rgba(14, 124, 66, 0.10);
            color: var(--green);
            font-size: 13px;
            font-weight: 700;
        }

        .seal {
            margin: 22px 0 0;
            padding: 16px 0 0;
            border-top: 1px solid var(--rule);
            color: var(--faint);
            font-size: 12px;
            line-height: 1.6;
        }

        .seal strong { color: var(--muted); font-weight: 600; }

        /* Not-recognised: sober, and it tells the holder what to do next. */
        .issuer.invalid { background: #8E2F23; }
        .refused { margin: 0; color: var(--muted); font-size: 15px; text-align: left; }
        .code {
            margin: 18px 0 0;
            font-family: ui-monospace, Menlo, Consolas, monospace;
            font-size: 12px;
            letter-spacing: 0.08em;
            color: var(--faint);
        }

        @media (prefers-color-scheme: dark) {
            :root {
                --page: #0D1310;
                --card: #151D18;
                --ink: #E9EFEA;
                --muted: #A2AFA6;
                --faint: #7F8D84;
                --rule: #26332B;
                --gold: #D2A551;
            }

            .card { box-shadow: none; border: 1px solid var(--rule); }
            .name { color: #EAF3EC; }
            .days { background: rgba(14, 124, 66, 0.22); color: #7FD3A0; }
        }

        @media print {
            body { background: #FFFFFF; padding: 0; }
            .card { box-shadow: none; border: 1px solid #CCCCCC; max-width: 100%; }
        }
    </style>
</head>
<body>
<main class="card">
    <div class="issuer {{ $valid ? '' : 'invalid' }}">
        <img src="{{ asset('images/nimr-logo.png') }}" alt="National Institute for Medical Research">
        <p class="ministry">Ministry of Health</p>
        <p class="institute">National Institute for Medical Research</p>
    </div>

    @if ($valid)
        <div class="body">
            <p class="attestation">{{ $attended ? 'Verified participation' : 'Verified registration' }}</p>

            <h1 class="name">{{ $registrant['name'] }}</h1>
            @if ($registrant['institution'])
                <p class="institution">{{ $registrant['institution'] }}</p>
            @endif

            {{-- The claim is only ever as strong as the record behind it. --}}
            <p class="lead">{{ $attended ? 'attended the' : 'was a registered participant of the' }}</p>
            <p class="conference">{{ $title }}</p>

            <p class="where">
                {{ $conference['venue'] }}@if ($conference['venue'] && $dates)<br>@endif{{ $dates ?? $conference['year'] }}
            </p>

            @if ($registrant['days_attended'] > 1)
                <span class="days">Present on {{ $registrant['days_attended'] }} days of the conference</span>
            @elseif ($attended && $registrant['first_attended_at'])
                <span class="days">Present on {{ $registrant['first_attended_at']->translatedFormat('j F Y') }}</span>
            @endif

            <p class="seal">
                <strong>Verified from the conference register</strong><br>
                This record is held by the National Institute for Medical Research and was confirmed
                on {{ now()->translatedFormat('j F Y') }}.
            </p>
        </div>
    @else
        <div class="body">
            <p class="attestation" style="color: #8E2F23;">Badge not recognised</p>
            <p class="refused">
                No settled registration matches this badge. It may have been issued for a different
                conference, or the registration behind it is no longer complete.
            </p>
            <p class="code">{{ $code }}</p>
            <p class="seal">
                Please refer the holder to the registration desk{{ $conference['venue'] ? ' at '.$conference['venue'] : '' }}.
            </p>
        </div>
    @endif
</main>
</body>
</html>
