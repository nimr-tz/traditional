<x-email.layout
    title="Payment needs attention"
    preheader="We could not verify your recent payment submission."
    eyebrow="Payment"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        We could not verify your payment for <strong style="color: #0f1b2d;">{{ $conferenceName }}</strong>. Please review the note below.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #f6f8fb; border-left: 4px solid #135eeb;">
        <tr>
            <td style="padding: 18px 20px; color: #27364b; font-size: 14px; line-height: 22px;">
                <strong style="color: #0f1b2d;">Finance note</strong><br>
                {{ $notes }}
            </td>
        </tr>
    </table>

    <x-email.button :url="$paymentUrl">Review your payment</x-email.button>

    <p style="margin: 18px 0 0; color: #5b687a; font-size: 13px; line-height: 21px;">
        If you paid by bank transfer, you can upload a corrected receipt from the payment page above.
    </p>

    <x-email.signoff />
</x-email.layout>
