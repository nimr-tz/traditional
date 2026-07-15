<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { font-family: 'Helvetica', sans-serif; margin: 0; padding: 0; }
    .sheet {
        width: 100%; height: 100vh; box-sizing: border-box;
        background: #faf6ee; border: 10px solid #2f5233; padding: 50px 70px;
        text-align: center;
    }
    .eyebrow { font-size: 13px; letter-spacing: 3px; text-transform: uppercase; color: #c1652f; }
    .title { font-size: 34px; font-weight: bold; color: #2f5233; margin-top: 12px; }
    .conference { font-size: 16px; color: #444; margin-top: 6px; }
    .presented-to { font-size: 13px; color: #888; margin-top: 40px; text-transform: uppercase; letter-spacing: 2px; }
    .name { font-size: 30px; font-weight: bold; color: #2f5233; margin-top: 10px; border-bottom: 2px solid #c1652f; display: inline-block; padding-bottom: 6px; }
    .institution { font-size: 14px; color: #555; margin-top: 10px; }
    .body-text { font-size: 13px; color: #444; margin-top: 30px; line-height: 1.6; }
    .footer { margin-top: 50px; display: table; width: 100%; }
    .footer-cell { display: table-cell; width: 50%; vertical-align: bottom; }
    .qr img { width: 90px; height: 90px; }
    .code { font-size: 9px; color: #999; font-family: monospace; margin-top: 6px; }
</style>
</head>
<body>
    <div class="sheet">
        <div class="eyebrow">Certificate of Participation</div>
        <div class="title">{{ $conferenceName }}</div>
        <div class="conference">{{ $conferenceYear }}</div>

        <div class="presented-to">This certificate is presented to</div>
        <div class="name">{{ $user->salutation }} {{ $user->name }}</div>
        <div class="institution">{{ $user->institution }}</div>

        <div class="body-text">
            for participation in the {{ $conferenceName }} ({{ $conferenceYear }}),<br>
            organized by the National Institute for Medical Research (NIMR).
        </div>

        <div class="footer">
            <div class="footer-cell" style="text-align: left;">
                <div class="code">Issued {{ $certificate->issued_at->format('d M Y') }}</div>
                <div class="code">Certificate No. {{ $certificate->certificate_code }}</div>
            </div>
            <div class="footer-cell qr" style="text-align: right;">
                <img src="{{ $qr }}" alt="Verification QR code">
            </div>
        </div>
    </div>
</body>
</html>
