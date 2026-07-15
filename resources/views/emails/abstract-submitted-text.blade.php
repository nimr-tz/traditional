{{ $isRevision ? 'Revised abstract received' : 'Abstract received' }}

Dear {{ $recipientName }},

We have received your {{ $isRevision ? 'revised ' : '' }}abstract submission for {{ $conferenceName }}.

Submitted abstract: {{ $submission->title }}

The reviewer will review it and notify you of the next decision by email.

View my submission:
{!! $submissionsUrl !!}

Kind regards,
{{ config('app.name') }} Team
