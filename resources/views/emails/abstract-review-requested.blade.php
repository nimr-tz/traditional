<x-email.layout
    :title="$isRevision ? 'Revised abstract ready for review' : 'New abstract ready for review'"
    :preheader="$isRevision ? 'An author has resubmitted a revised abstract.' : 'A new abstract is awaiting review.'"
    eyebrow="Reviewer action required"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        {{ $isRevision ? 'A revised abstract has been resubmitted and is ready for review.' : 'A new abstract has been submitted and is ready for review.' }}
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #f6f8fb; border-left: 4px solid #4c8a1f;">
        <tr>
            <td style="padding: 18px 20px; color: #27364b; font-size: 14px; line-height: 22px;">
                <strong style="color: #0f1b2d; font-family: Georgia, 'Times New Roman', serif; font-size: 17px;">{{ $submission->title }}</strong><br>
                {{ $submission->user->name }} · {{ ucfirst($submission->presentation_type) }} presentation
            </td>
        </tr>
    </table>

    <x-email.button :url="$reviewUrl">Read and review abstract</x-email.button>

    <x-email.signoff />
</x-email.layout>
