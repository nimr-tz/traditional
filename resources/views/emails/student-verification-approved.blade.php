<x-email.layout
    title="Student status verified"
    preheader="Your student document has been approved."
    eyebrow="Student verification"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Your student document has been reviewed and your student status is now verified.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #eef7e8;">
        <tr>
            <td style="padding: 17px 20px; color: #24422b; font-size: 14px; line-height: 22px;">
                You can now continue to payment using your selected student registration category.
            </td>
        </tr>
    </table>

    <x-email.button :url="$paymentUrl">Continue to payment</x-email.button>

    <x-email.signoff />
</x-email.layout>
