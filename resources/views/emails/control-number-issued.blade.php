<x-email.layout
    title="Your control number is ready"
    preheader="Use this control number to pay your TMSC registration fee."
    eyebrow="Payment"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Your control number for <strong style="color: #0f1b2d;">{{ $conferenceName }}</strong> has been issued. Use it to pay your
        registration fee through your preferred GePG channel (bank, mobile money, or another supported payment method).
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #eaf1ff;">
        <tr>
            <td style="padding: 17px 20px; color: #1a2f52; font-size: 14px; line-height: 22px;">
                Control number<br>
                <strong style="color: #0f1b2d; font-size: 22px; letter-spacing: 0.5px;">{{ $user->control_number }}</strong>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 0 0 22px;">
        <tr>
            <td style="padding: 6px 0; color: #27364b; font-size: 14px; line-height: 22px;">
                Amount due<br>
                <strong style="color: #0f1b2d;">{{ $user->currency }} {{ number_format((float) $user->fee_amount, 2) }}</strong>
            </td>
        </tr>
        <tr>
            <td style="padding: 6px 0; color: #27364b; font-size: 14px; line-height: 22px;">
                Payable to<br>
                <strong style="color: #0f1b2d;">{{ $gepgPayeeName }}</strong>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Once your payment is received, we'll confirm it by email and your registration will be complete.
    </p>

    <x-email.signoff />
</x-email.layout>
