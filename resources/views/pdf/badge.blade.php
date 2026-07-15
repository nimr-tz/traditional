<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    body { font-family: 'Helvetica', sans-serif; margin: 0; padding: 0; }
    .badge {
        width: 306px; height: 468px; box-sizing: border-box;
        background: #faf6ee; border: 4px solid #2f5233;
        padding: 24px 20px; text-align: center;
    }
    .conference-name { font-size: 13px; letter-spacing: 1px; text-transform: uppercase; color: #2f5233; font-weight: bold; }
    .conference-year { font-size: 11px; color: #c1652f; margin-top: 2px; }
    .divider { border-top: 2px solid #c1652f; margin: 16px auto; width: 60px; }
    .name { font-size: 22px; font-weight: bold; color: #2f5233; margin-top: 20px; }
    .institution { font-size: 13px; color: #444; margin-top: 6px; }
    .category { display: inline-block; margin-top: 16px; padding: 6px 14px; background: #2f5233; color: #fff; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; border-radius: 3px; }
    .qr { margin-top: 28px; }
    .qr img { width: 130px; height: 130px; }
    .code { font-size: 10px; color: #888; margin-top: 8px; font-family: monospace; }
</style>
</head>
<body>
    <div class="badge">
        <div class="conference-name">{{ $conferenceName }}</div>
        <div class="conference-year">{{ $conferenceYear }}</div>
        <div class="divider"></div>

        <div class="name">{{ $user->salutation }} {{ $user->name }}</div>
        <div class="institution">{{ $user->institution }}</div>

        <div class="category">{{ $feeCategoryLabel }}</div>

        <div class="qr">
            <img src="{{ $qr }}" alt="QR code">
        </div>
        <div class="code">{{ $user->registration_code }}</div>
    </div>
</body>
</html>
