<x-email.layout
    title="Student document needs attention"
    preheader="Please review the feedback and submit another student document."
    eyebrow="Student verification"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        We could not verify the student document you submitted. Please review the note below and submit another document.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #f6f8fb; border-left: 4px solid #135eeb;">
        <tr>
            <td style="padding: 18px 20px; color: #27364b; font-size: 14px; line-height: 22px;">
                <strong style="color: #0f1b2d;">Verification note</strong><br>
                {{ $notes }}
            </td>
        </tr>
    </table>

    <x-email.button :url="$paymentUrl">Submit another document</x-email.button>

    <p style="margin: 0 0 18px; color: #5b687a; font-size: 13px; line-height: 21px;">
        Payment at the student rate remains unavailable until the replacement document is verified.
    </p>

    <x-email.signoff />
</x-email.layout>
