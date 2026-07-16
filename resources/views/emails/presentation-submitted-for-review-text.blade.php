{{ $isReplacement ? 'Replacement presentation awaiting review' : 'New presentation awaiting review' }}

Dear {{ $recipientName }},

{{ $submission->user->name }} has uploaded a {{ $isReplacement ? 'replacement ' : '' }}presentation file for review.

Abstract: {{ $submission->title }}

Review presentation:
{!! $reviewUrl !!}

Kind regards,
{{ config('app.name') }} Team
