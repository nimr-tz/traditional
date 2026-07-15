@if ($submission->status === 'accepted')
Congratulations!

Dear {{ $recipientName }},

Your abstract "{{ $submission->title }}" has been accepted as a {{ ucfirst($submission->presentation_type) }} presentation.
@elseif ($submission->status === 'revision_requested')
Revision requested

Dear {{ $recipientName }},

The reviewer has requested revisions to your abstract "{{ $submission->title }}". Review the comments, update your abstract, and resubmit it for another review.
@else
Abstract decision

Dear {{ $recipientName }},

After careful review, your abstract "{{ $submission->title }}" was not accepted for this year's conference.
@endif

@if ($submission->decision_notes)
Reviewer comments:
{{ $submission->decision_notes }}

@endif
View my submission:
{!! $submissionsUrl !!}

Kind regards,
{{ config('app.name') }} Team
