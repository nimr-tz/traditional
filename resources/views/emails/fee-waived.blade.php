<x-email.layout
    title="Registration fee waived"
    preheader="Your registration fee has been waived."
    eyebrow="Payment"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Your registration fee for <strong style="color: #0f1b2d;">{{ $conferenceName }}</strong> has been waived by our finance team. No payment is required.
    </p>

    @if($notes)
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #eef7e8;">
        <tr>
            <td style="padding: 17px 20px; color: #24422b; font-size: 14px; line-height: 22px;">
                <strong style="color: #17351f;">Note</strong><br>
                {{ $notes }}
            </td>
        </tr>
    </table>
    @endif

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Your registration is now complete and confirmed.
    </p>

    <x-email.signoff />
</x-email.layout>
