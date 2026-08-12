<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- A badge scan should not end up in a search index. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $valid ? 'Verified badge' : 'Badge not recognised' }} · {{ $conferenceName }}</title>
    <style>
        :root { color-scheme: light dark; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            background: #f4f6fb;
            color: #17233a;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(19, 35, 58, 0.12);
        }

        .banner { padding: 26px 24px; text-align: center; color: #ffffff; }
        .banner.valid { background: #2f7d32; }
        .banner.invalid { background: #a3271f; }

        .mark { font-size: 34px; line-height: 1; }
        .banner h1 { margin: 12px 0 0; font-size: 19px; letter-spacing: 0.01em; }
        .banner p { margin: 6px 0 0; font-size: 13px; opacity: 0.9; }

        .body { padding: 24px; }

        .name { margin: 0; font-size: 24px; font-weight: 700; }
        .institution { margin: 6px 0 0; color: #55627a; font-size: 15px; }

        .chip {
            display: inline-block;
            margin-top: 14px;
            padding: 6px 14px;
            border-radius: 999px;
            background: #eaf1ff;
            color: #14479c;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        dl { margin: 22px 0 0; padding: 18px 0 0; border-top: 1px solid #e6e9f2; }
        .row { display: flex; justify-content: space-between; gap: 16px; padding: 7px 0; font-size: 14px; }
        dt { color: #6b7689; margin: 0; }
        dd { margin: 0; font-weight: 600; text-align: right; }

        .code { margin: 20px 0 0; font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12px; color: #8a94a6; text-align: center; letter-spacing: 0.08em; }
        .footer { padding: 0 24px 24px; color: #6b7689; font-size: 12px; text-align: center; }

        @media (prefers-color-scheme: dark) {
            body { background: #0e131d; color: #e8ecf5; }
            .card { background: #161d2b; box-shadow: none; }
            .institution, dt, .footer, .code { color: #96a1b6; }
            dl { border-top-color: #263042; }
            .chip { background: rgba(19, 94, 235, 0.18); color: #93b6ff; }
        }
    </style>
</head>
<body>
<main class="card">
    @if ($valid)
        <div class="banner valid">
            <div class="mark">&check;</div>
            <h1>Registered attendee</h1>
            <p>{{ $conferenceName }}{{ $conferenceYear ? ' · '.$conferenceYear : '' }}</p>
        </div>

        <div class="body">
            <p class="name">{{ $registrant['name'] }}</p>
            @if ($registrant['institution'])
                <p class="institution">{{ $registrant['institution'] }}</p>
            @endif
            @if ($registrant['category'])
                <span class="chip">{{ $registrant['category'] }}</span>
            @endif

            <dl>
                <div class="row">
                    <dt>Attendance today</dt>
                    <dd>{{ $registrant['attended_today'] ? 'Checked in' : 'Not yet scanned' }}</dd>
                </div>
                <div class="row">
                    <dt>Days attended</dt>
                    <dd>{{ $registrant['days_attended'] }}</dd>
                </div>
                @if ($registrant['last_attended_at'])
                    <div class="row">
                        <dt>Last seen</dt>
                        <dd>{{ $registrant['last_attended_at']->translatedFormat('j M, H:i') }}</dd>
                    </div>
                @endif
            </dl>

            <p class="code">{{ $code }}</p>
        </div>

        <p class="footer">Scanned by conference staff, this badge also records the day's attendance.</p>
    @else
        <div class="banner invalid">
            <div class="mark">&times;</div>
            <h1>Badge not recognised</h1>
            <p>{{ $conferenceName }}{{ $conferenceYear ? ' · '.$conferenceYear : '' }}</p>
        </div>

        <div class="body">
            <p class="institution">
                No live registration matches this badge. It may have been issued for a different conference, or the
                registration behind it is no longer settled.
            </p>
            <p class="code">{{ $code }}</p>
        </div>

        <p class="footer">Please refer the holder to the registration desk{{ $venue ? ' at '.$venue : '' }}.</p>
    @endif
</main>
</body>
</html>
