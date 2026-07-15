<x-email.layout
    :title="match ($submission->status) { 'accepted' => 'Congratulations!', 'revision_requested' => 'Revision requested', default => 'Abstract decision' }"
    preheader="A decision is available for your abstract submission."
    eyebrow="Abstract decision"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        Dear {{ $recipientName }},
    </p>

    @if ($submission->status === 'accepted')
        <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
            Your abstract <strong style="color: #0f1b2d;">{{ $submission->title }}</strong> has been accepted as a <strong style="color: #0f1b2d;">{{ ucfirst($submission->presentation_type) }} presentation</strong>.
        </p>
    @elseif ($submission->status === 'revision_requested')
        <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
            The reviewer has requested revisions to your abstract <strong style="color: #0f1b2d;">{{ $submission->title }}</strong>. Please review the comments below, update your abstract, and resubmit it for another review.
        </p>
    @else
        <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
            After careful review, your abstract <strong style="color: #0f1b2d;">{{ $submission->title }}</strong> was not accepted for this year's conference.
        </p>
    @endif

    @if ($submission->decision_notes)
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #f6f8fb; border-left: 4px solid #135eeb;">
            <tr>
                <td style="padding: 18px 20px; color: #27364b; font-size: 14px; line-height: 22px;">
                    <strong style="color: #0f1b2d;">Reviewer comments</strong><br>
                    {{ $submission->decision_notes }}
                </td>
            </tr>
        </table>
    @endif

    <x-email.button :url="$submissionsUrl">{{ $submission->status === 'revision_requested' ? 'Revise my abstract' : 'View my submission' }}</x-email.button>

    <x-email.signoff />
</x-email.layout>
