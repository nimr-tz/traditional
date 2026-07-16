<x-email.layout
    title="Please resubmit your presentation"
    preheader="Your presentation file needs a revision before it can be approved."
    eyebrow="Presentation"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Your presentation file for <strong style="color: #0f1b2d;">{{ $conferenceName }}</strong> needs a revision before it can be approved.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #f6f8fb; border-left: 4px solid #135eeb;">
        <tr>
            <td style="padding: 18px 20px; color: #27364b; font-size: 14px; line-height: 22px;">
                Abstract<br>
                <strong style="color: #0f1b2d; font-family: Georgia, 'Times New Roman', serif; font-size: 17px;">{{ $submission->title }}</strong>
            </td>
        </tr>
    </table>

    @if ($submission->presentation_review_notes)
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 0 0 22px; background-color: #fbeeea;">
            <tr>
                <td style="padding: 17px 20px; color: #7a2e1d; font-size: 14px; line-height: 22px;">
                    Reviewer notes<br>
                    <strong style="color: #5c2115;">{{ $submission->presentation_review_notes }}</strong>
                </td>
            </tr>
        </table>
    @endif

    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Please log in and upload a replacement file at your earliest convenience.
    </p>

    <x-email.signoff />
</x-email.layout>
