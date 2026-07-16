<x-email.layout
    :title="$isReplacement ? 'Replacement presentation received' : 'Presentation received'"
    :preheader="$isReplacement ? 'We have received your replacement presentation file.' : 'We have received your presentation file.'"
    eyebrow="Presentation"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        We have received your {{ $isReplacement ? 'replacement ' : '' }}presentation file for
        <strong style="color: #0f1b2d;">{{ $conferenceName }}</strong>.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #f6f8fb; border-left: 4px solid #135eeb;">
        <tr>
            <td style="padding: 18px 20px; color: #27364b; font-size: 14px; line-height: 22px;">
                Abstract<br>
                <strong style="color: #0f1b2d; font-family: Georgia, 'Times New Roman', serif; font-size: 17px;">{{ $submission->title }}</strong>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        The organizers will review it before the conference. You'll be notified by email once it's approved.
    </p>

    <x-email.signoff />
</x-email.layout>
