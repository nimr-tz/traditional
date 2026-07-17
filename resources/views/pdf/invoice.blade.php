<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'Helvetica', sans-serif; color: #27364b; margin: 0; padding: 40px; }
    .eyebrow { font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #c1652f; font-weight: bold; }
    h1 { font-size: 26px; color: #2f5233; margin: 6px 0 28px; }
    .meta { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
    .meta td { padding: 6px 0; font-size: 12px; vertical-align: top; }
    .meta td.label { color: #6b7280; width: 160px; }
    .meta td.value { font-weight: bold; }
    table.lines { width: 100%; border-collapse: collapse; margin-top: 12px; }
    table.lines th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6b7280; border-bottom: 2px solid #2f5233; padding: 8px 0; }
    table.lines td { padding: 14px 0; border-bottom: 1px solid #e5e7eb; font-size: 13px; }
    .amount { text-align: right; font-size: 20px; font-weight: bold; color: #2f5233; padding-top: 16px; }
    .footer { margin-top: 40px; font-size: 10px; color: #9ca3af; }
</style>
</head>
<body>
    <div class="eyebrow">Invoice</div>
    <h1>{{ $payeeName }}</h1>

    <table class="meta">
        <tr>
            <td class="label">Billed to</td>
            <td class="value">{{ $user->salutation }} {{ $user->name }}<br>{{ $user->email }}</td>
        </tr>
        <tr>
            <td class="label">Billing ID</td>
            <td class="value">{{ $user->billing_request_id }}</td>
        </tr>
        <tr>
            <td class="label">Control number</td>
            <td class="value">{{ $user->control_number }}</td>
        </tr>
        <tr>
            <td class="label">Status</td>
            <td class="value">{{ $user->payment_status === 'verified' ? 'Paid' : 'Awaiting payment' }}</td>
        </tr>
        <tr>
            <td class="label">Date issued</td>
            <td class="value">{{ now()->format('M d, Y') }}</td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $categoryLabel }} registration fee</td>
                <td style="text-align: right;">{{ $user->currency }} {{ number_format((float) $user->fee_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="amount">Total due: {{ $user->currency }} {{ number_format((float) $user->fee_amount, 2) }}</div>

    <div class="footer">Pay using the control number above through any GePG-enabled bank, mobile money, or payment channel.</div>
</body>
</html>
