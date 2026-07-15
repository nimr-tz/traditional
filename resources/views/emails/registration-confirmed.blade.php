<x-email.layout
    title="Registration received"
    preheader="We have received your conference registration."
    eyebrow="Registration"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Thank you for registering for <strong style="color: #0f1b2d;">{{ $conferenceName }}</strong>.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #eef7e8;">
        <tr>
            <td style="padding: 17px 20px; color: #24422b; font-size: 14px; line-height: 22px;">
                Registration category<br>
                <strong style="color: #17351f; font-size: 16px;">{{ $feeCategoryLabel }}</strong>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        You can sign in at any time to view your participation fee and start your payment.
    </p>

    <x-email.button :url="$dashboardUrl">Go to my dashboard</x-email.button>

    <x-email.signoff />
</x-email.layout>
