<x-email.layout
    :title="$isRevision ? 'Abstract ready for re-review' : 'New abstract ready for review'"
    :preheader="$isRevision ? 'An abstract you reviewed has been revised and needs another look.' : 'A new abstract is awaiting review.'"
    :eyebrow="$isRevision ? 'Re-review requested' : 'Reviewer action required'"
    :conference-name="$conferenceName"
    :logo-url="$logoUrl"
>
    <p style="margin: 0 0 18px; color: #27364b; font-size: 16px; line-height: 26px;">
        {{ $isRevision
            ? 'An abstract you previously reviewed has been revised. Your earlier recommendation and comments are shown alongside it, so you can see whether the author addressed them.'
            : 'A new abstract has been submitted and is ready for review.' }}
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width: 100%; margin: 26px 0; background-color: #f6f8fb; border-left: 4px solid #4c8a1f;">
        <tr>
            <td style="padding: 18px 20px; color: #27364b; font-size: 14px; line-height: 22px;">
                {{-- Blind review: never name the author here. --}}
                <strong style="color: #0f1b2d; font-family: Georgia, 'Times New Roman', serif; font-size: 17px;">{{ $submission->title }}</strong><br>
                {{ $submission->subtheme?->title ?? 'No sub-theme' }} · {{ ucfirst($submission->presentation_type) }} presentation
            </td>
        </tr>
    </table>

    <x-email.button :url="$reviewUrl">{{ $isRevision ? 'Open re-review' : 'Read and review abstract' }}</x-email.button>

    <x-email.signoff />
</x-email.layout>
