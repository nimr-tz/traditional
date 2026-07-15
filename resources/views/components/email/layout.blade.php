@props([
    'title',
    'preheader',
    'eyebrow',
    'conferenceName',
    'logoUrl',
    'footer' => null,
])
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $title }}</title>
    <style>
        @media only screen and (max-width: 640px) {
            .email-shell { width: 100% !important; }
            .email-padding { padding-left: 24px !important; padding-right: 24px !important; }
            .email-title { font-size: 30px !important; line-height: 36px !important; }
            .email-button { display: block !important; text-align: center !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f3f6fb; color: #0f1b2d; font-family: 'Segoe UI', Tahoma, sans-serif; -webkit-text-size-adjust: 100%;">
    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; color: transparent;">
        {{ $preheader }}
    </div>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; background-color: #f3f6fb;">
        <tr>
            <td align="center" style="padding: 36px 16px;">
                <table role="presentation" class="email-shell" width="600" cellspacing="0" cellpadding="0" border="0" style="width: 600px; max-width: 600px; background-color: #ffffff; border: 1px solid #dce5f3;">
                    <tr>
                        <td style="height: 5px; background-color: #135eeb; font-size: 0; line-height: 0;">&nbsp;</td>
                        <td style="height: 5px; background-color: #67b52f; font-size: 0; line-height: 0;">&nbsp;</td>
                    </tr>
                    <tr>
                        <td colspan="2" class="email-padding" style="padding: 30px 48px 24px; border-bottom: 1px solid #e4eaf3;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                                <tr>
                                    <td width="72" valign="middle">
                                        <img src="{{ $logoUrl }}" width="56" height="56" alt="NIMR" style="display: block; width: 56px; height: 56px; border: 0; object-fit: contain;">
                                    </td>
                                    <td valign="middle" style="padding-left: 14px;">
                                        <p style="margin: 0 0 5px; color: #135eeb; font-size: 11px; line-height: 15px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;">
                                            {{ $eyebrow }}
                                        </p>
                                        <p style="margin: 0; color: #0f1b2d; font-family: Georgia, 'Times New Roman', serif; font-size: 17px; line-height: 23px; font-weight: 700;">
                                            {{ $conferenceName }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="email-padding" style="padding: 40px 48px 44px;">
                            <h1 class="email-title" style="margin: 0 0 24px; color: #0f1b2d; font-family: Georgia, 'Times New Roman', serif; font-size: 36px; line-height: 42px; font-weight: 700; letter-spacing: -0.4px;">
                                {{ $title }}
                            </h1>

                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="email-padding" style="padding: 22px 48px; background-color: #0f1b2d;">
                            <p style="margin: 0; color: #c8d3e3; font-size: 11px; line-height: 18px; text-align: center;">
                                {{ $footer ?: 'This is a transactional message from '.config('app.name').'.' }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
