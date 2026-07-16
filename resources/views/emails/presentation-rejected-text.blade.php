Please resubmit your presentation

Dear {{ $recipientName }},

Your presentation file for {{ $conferenceName }} needs a revision before it can be approved.

Abstract: {{ $submission->title }}
@if ($submission->presentation_review_notes)

Reviewer notes: {{ $submission->presentation_review_notes }}
@endif

Please log in and upload a replacement file at your earliest convenience.

Kind regards,
{{ config('app.name') }} Team
