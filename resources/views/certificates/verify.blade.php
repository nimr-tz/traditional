<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Certificate Verification — {{ $conferenceName }}</title>
<style>
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #faf6ee; color: #2f2f2f; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
    .card { background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 40px; max-width: 420px; width: 90%; text-align: center; }
    .icon { font-size: 40px; }
    .valid { color: #2f5233; }
    .invalid { color: #b3402a; }
    h1 { font-size: 20px; margin: 12px 0 4px; }
    p { color: #666; font-size: 14px; }
    .detail { margin-top: 20px; text-align: left; font-size: 14px; }
    .detail div { padding: 6px 0; border-bottom: 1px solid #eee; }
    .detail span { color: #888; }
</style>
</head>
<body>
    <div class="card">
        @if ($certificate)
            <div class="icon valid">&#10003;</div>
            <h1 class="valid">Certificate Verified</h1>
            <p>This certificate is genuine and was issued by NIMR.</p>
            <div class="detail">
                <div><span>Name:</span> {{ $certificate->user->salutation }} {{ $certificate->user->name }}</div>
                <div><span>Institution:</span> {{ $certificate->user->institution }}</div>
                <div><span>Conference:</span> {{ $conferenceName }}</div>
                <div><span>Issued:</span> {{ $certificate->issued_at->format('d M Y') }}</div>
                <div><span>Certificate No.:</span> {{ $certificate->certificate_code }}</div>
            </div>
        @else
            <div class="icon invalid">&#10007;</div>
            <h1 class="invalid">Certificate Not Found</h1>
            <p>We could not verify a certificate with this code.</p>
        @endif
    </div>
</body>
</html>
