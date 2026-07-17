<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'Helvetica', sans-serif; color: #27364b; margin: 0; padding: 40px; }
    .eyebrow { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #4c8a1f; font-weight: bold; }
    h1 { font-size: 26px; color: #2f5233; margin: 6px 0 28px; }
    .stamp { display: inline-block; padding: 6px 14px; background: #eef7e6; color: #2f5233; font-size: 11px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; border-radius: 3px; margin-bottom: 24px; }
    .meta { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .meta td { padding: 6px 0; font-size: 12px; vertical-align: top; }
    .meta td.label { color: #6b7280; width: 160px; }
    .meta td.value { font-weight: bold; }
    .amount { text-align: right; font-size: 22px; font-weight: bold; color: #2f5233; padding-top: 16px; border-top: 2px solid #2f5233; margin-top: 12px; }
    .footer { margin-top: 40px; font-size: 10px; color: #9ca3af; }
</style>
</head>
<body>
    <div class="eyebrow">Receipt</div>
    <h1>{{ $payeeName }}</h1>
    <div class="stamp">Payment received{{ $user->payment_status === 'waived' ? ' — fee waived' : '' }}</div>

    <table class="meta">
        <tr>
            <td class="label">Paid by</td>
            <td class="value">{{ $user->salutation }} {{ $user->name }}<br>{{ $user->email }}</td>
        </tr>
        <tr>
            <td class="label">Registration category</td>
            <td class="value">{{ $categoryLabel }}</td>
        </tr>
        <tr>
            <td class="label">Billing ID</td>
            <td class="value">{{ $user->billing_request_id }}</td>
        </tr>
        <tr>
            <td class="label">Control number</td>
            <td class="value">{{ $user->control_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Date paid</td>
            <td class="value">{{ $user->paid_at?->format('M d, Y') ?? '—' }}</td>
        </tr>
        @if($user->registration_code)
        <tr>
            <td class="label">Registration code</td>
            <td class="value">{{ $user->registration_code }}</td>
        </tr>
        @endif
    </table>

    <div class="amount">Amount paid: {{ $user->currency }} {{ number_format((float) $user->fee_amount, 2) }}</div>

    <div class="footer">This receipt confirms your TMSC registration payment. Keep it for your records.</div>
</body>
</html>
