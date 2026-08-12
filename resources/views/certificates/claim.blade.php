<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Get your certificate · {{ $conferenceName }}</title>
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
            line-height: 1.55;
        }

        .card {
            width: 100%;
            max-width: 460px;
            background: #ffffff;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(19, 35, 58, 0.12);
        }

        .banner { background: #0d3fa8; color: #ffffff; padding: 26px 26px 22px; }
        .banner p { margin: 0; font-size: 11px; letter-spacing: 0.18em; text-transform: uppercase; color: #8fd45a; font-weight: 700; }
        .banner h1 { margin: 10px 0 0; font-size: 22px; }
        .banner span { display: block; margin-top: 6px; font-size: 13px; opacity: 0.8; }

        form { padding: 24px 26px 26px; }
        label { display: block; font-size: 13px; font-weight: 700; margin-bottom: 6px; }
        .hint { font-weight: 400; color: #6b7689; font-size: 12px; margin: 0 0 8px; }

        input {
            width: 100%;
            min-height: 46px;
            padding: 10px 13px;
            margin-bottom: 18px;
            border: 1px solid #cfd6e4;
            border-radius: 10px;
            background: #ffffff;
            color: #17233a;
            font-size: 16px;
        }

        button {
            width: 100%;
            min-height: 50px;
            border: 0;
            border-radius: 10px;
            background: #4c8a1f;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
        }

        button:hover { background: #3f751a; }

        .notice { margin: 0 0 18px; padding: 13px 15px; border-radius: 10px; font-size: 14px; }
        .notice.error { background: #fdeceb; color: #94271d; }
        .notice.pending { background: #fbf1dc; color: #7a5216; }

        .footer { padding: 0 26px 26px; margin: 0; font-size: 12px; color: #6b7689; }
        .footer a { color: #14479c; }

        @media (prefers-color-scheme: dark) {
            body { background: #0e131d; color: #e8ecf5; }
            .card { background: #161d2b; box-shadow: none; }
            input { background: #0e131d; border-color: #2b3446; color: #e8ecf5; }
            .hint, .footer { color: #96a1b6; }
        }
    </style>
</head>
<body>
<main class="card">
    <div class="banner">
        <p>{{ $conferenceName }}{{ $conferenceYear ? ' · '.$conferenceYear : '' }}</p>
        <h1>Get your certificate</h1>
        <span>No account needed — you attended, so it is yours.</span>
    </div>

    <form method="POST" action="{{ route('certificate.claim.submit') }}">
        @csrf

        @if (session('error'))
            <p class="notice error">{{ session('error') }}</p>
        @endif

        @if (! $window['is_open'])
            <p class="notice pending">{{ $window['pending_message'] }}</p>
        @endif

        @error('name')
            <p class="notice error">{{ $message }}</p>
        @enderror
        @error('proof')
            <p class="notice error">{{ $message }}</p>
        @enderror

        <label for="name">Your full name</label>
        <p class="hint">As given when you registered.</p>
        <input id="name" name="name" value="{{ old('name') }}" required autocomplete="name">

        <label for="proof">Badge code, phone number or email</label>
        <p class="hint">
            The code on your badge (TMSC-…) is the surest. Otherwise the phone number or email you gave at
            registration.
        </p>
        <input id="proof" name="proof" required autocomplete="off">

        <button type="submit">Download my certificate</button>
    </form>

    <p class="footer">
        Certificates are issued to attendees whose arrival was recorded at the venue. If yours will not download,
        please ask at the registration desk.
    </p>
</main>
</body>
</html>
