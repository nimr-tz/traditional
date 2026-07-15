<x-email.layout
    :title="$isRevision ? 'Revised abstract received' : 'Abstract received'"
    :preheader="$isRevision ? 'We have received your revised abstract.' : 'We have received your abstract submission.'"
    eyebrow="Abstract submission"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        We have received your {{ $isRevision ? 'revised ' : '' }}abstract submission for <strong style="color: #0f1b2d;">{{ $conferenceName }}</strong>.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #f6f8fb; border-left: 4px solid #135eeb;">
        <tr>
            <td style="padding: 18px 20px; color: #27364b; font-size: 14px; line-height: 22px;">
                Submitted abstract<br>
                <strong style="color: #0f1b2d; font-family: Georgia, 'Times New Roman', serif; font-size: 17px;">{{ $submission->title }}</strong>
            </td>
        </tr>
    </table>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        The reviewer will review it and notify you of the next decision by email.
    </p>

    <x-email.button :url="$submissionsUrl">View my submission</x-email.button>

    <x-email.signoff />
</x-email.layout>
