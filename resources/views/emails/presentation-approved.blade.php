<x-email.layout
    title="Presentation approved"
    preheader="Your presentation file has been approved."
    eyebrow="Presentation"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Your presentation file for <strong style="color: #0f1b2d;">{{ $conferenceName }}</strong> has been reviewed and approved.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #eef7e8;">
        <tr>
            <td style="padding: 17px 20px; color: #24422b; font-size: 14px; line-height: 22px;">
                Abstract<br>
                <strong style="color: #17351f; font-family: Georgia, 'Times New Roman', serif; font-size: 17px;">{{ $submission->title }}</strong>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        No further action is needed. We look forward to your presentation at the conference.
    </p>

    <x-email.signoff />
</x-email.layout>
